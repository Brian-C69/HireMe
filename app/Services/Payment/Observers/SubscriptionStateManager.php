<?php

declare(strict_types=1);

namespace App\Services\Payment\Observers;

use App\Models\Candidate;
use App\Models\Employer;
use App\Models\Recruiter;
use App\Services\Payment\PaymentEvent;
use App\Services\Payment\PaymentObserver;
use App\Services\Payment\PaymentProcessor;

final class SubscriptionStateManager implements PaymentObserver
{
    public function handle(PaymentEvent $event): void
    {
        if ($event->name() !== PaymentProcessor::EVENT_INVOICE_PAID) {
            return;
        }

        $payload = $event->payload();
        $userType = (string) ($payload['user_type'] ?? '');
        $userId = (int) ($payload['user_id'] ?? 0);
        $credits = $payload['credits'] ?? null;
        $metadata = $payload['metadata'] ?? [];

        if ($credits === null && is_array($metadata) && isset($metadata['credits'])) {
            $credits = $metadata['credits'];
        }

        if (is_numeric($credits) && (int) $credits > 0) {
            $this->applyCredits($userType, $userId, (int) $credits);
        }

        if ($this->isPremiumPurchase($payload) && strcasecmp($userType, 'Candidate') === 0) {
            $this->activatePremiumBadge($userId);
        }
    }

    private function applyCredits(string $userType, int $userId, int $credits): void
    {
        if ($credits <= 0 || $userId <= 0) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');

        if (strcasecmp($userType, 'Employer') === 0) {
            Employer::query()->where('employer_id', $userId)->increment('credits_balance', $credits, [
                'updated_at' => $timestamp,
            ]);

            return;
        }

        if (strcasecmp($userType, 'Recruiter') === 0) {
            Recruiter::query()->where('recruiter_id', $userId)->increment('credits_balance', $credits, [
                'updated_at' => $timestamp,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isPremiumPurchase(array $payload): bool
    {
        $purpose = strtolower((string) ($payload['purpose'] ?? ''));
        if ($purpose === '') {
            $purpose = strtolower((string) ($payload['raw_purpose'] ?? ''));
        }

        return str_contains($purpose, 'premium');
    }

    private function activatePremiumBadge(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');

        Candidate::query()->where('candidate_id', $userId)->update([
            'premium_badge' => 1,
            'premium_badge_date' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}
