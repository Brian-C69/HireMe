<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use App\Services\Admin\Moderation\AdminRequestAuthorizer;
use App\Services\Admin\Moderation\Commands\ApproveJobCommand;
use App\Services\Admin\Moderation\Commands\AuditLogCommand;
use App\Services\Admin\Moderation\Commands\MetricsCommand;
use App\Services\Admin\Moderation\Commands\OverviewCommand;
use App\Services\Admin\Moderation\Commands\ReinstateUserCommand;
use App\Services\Admin\Moderation\Commands\SuspendUserCommand;
use App\Services\Admin\Moderation\ErrorLogModerationLogger;
use App\Services\Admin\Moderation\ModerationCommandBus;
use App\Services\Admin\Moderation\ModerationCommandResult;
use App\Services\Admin\Moderation\ModerationSuspensionStore;
use App\Services\Admin\Moderation\UserLookup;
use DateTimeImmutable;
use InvalidArgumentException;

final class AdminModerationService extends AbstractModuleService
{
    private ?ModerationSuspensionStore $suspensionStore = null;
    private ?UserLookup $userLookup = null;

    public function name(): string
    {
        return 'admin-moderation';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        $bus = $this->makeCommandBus($request);
        $type = strtolower($type);

        return match ($type) {
            'overview' => $this->respond($bus->dispatch(new OverviewCommand($this->registry, $this->suspensionStore()))->data()),
            'metrics' => $this->respond($bus->dispatch(new MetricsCommand($this->suspensionStore()))->data()),
            'audit' => $this->respond($bus->dispatch(new AuditLogCommand($this->registry, $this->suspensionStore()))->data()),
            'approve-job' => $this->respondFromResult($bus->dispatch($this->makeApproveJobCommand($request, $id))),
            'suspend-user' => $this->respondFromResult($bus->dispatch($this->makeSuspendUserCommand($request))),
            'reinstate-user' => $this->respondFromResult($bus->dispatch($this->makeReinstateUserCommand($request))),
            default => throw new InvalidArgumentException(sprintf('Unknown administration operation "%s".', $type)),
        };
    }

    private function makeCommandBus(Request $request): ModerationCommandBus
    {
        return new ModerationCommandBus(
            new AdminRequestAuthorizer($request),
            new ErrorLogModerationLogger()
        );
    }

    private function respondFromResult(ModerationCommandResult $result): array
    {
        return $this->respond([
            'result' => $result->toArray(),
        ]);
    }

    private function makeApproveJobCommand(Request $request, ?string $id): ApproveJobCommand
    {
        $jobId = $this->requireIntId($id, 'A job identifier is required.');

        return new ApproveJobCommand($jobId, $this->moderatorId($request));
    }

    private function makeSuspendUserCommand(Request $request): SuspendUserCommand
    {
        $role = $this->requireUserRole($request);
        $userId = $this->requireUserId($request);
        $until = $this->parseSuspensionUntil($request);
        $reason = $this->suspensionReason($request);

        return new SuspendUserCommand(
            $role,
            $userId,
            $this->suspensionStore(),
            $this->userLookup(),
            $until,
            $reason,
            $this->moderatorId($request)
        );
    }

    private function makeReinstateUserCommand(Request $request): ReinstateUserCommand
    {
        $role = $this->requireUserRole($request);
        $userId = $this->requireUserId($request);

        return new ReinstateUserCommand(
            $role,
            $userId,
            $this->suspensionStore(),
            $this->userLookup(),
            $this->moderatorId($request)
        );
    }

    private function suspensionStore(): ModerationSuspensionStore
    {
        if ($this->suspensionStore === null) {
            $this->suspensionStore = new ModerationSuspensionStore();
        }

        return $this->suspensionStore;
    }

    private function userLookup(): UserLookup
    {
        if ($this->userLookup === null) {
            $this->userLookup = new UserLookup();
        }

        return $this->userLookup;
    }

    private function moderatorId(Request $request): ?int
    {
        $candidates = [
            $request->header('X-Admin-Id'),
            $request->header('X-Moderator-Id'),
            $request->input('moderator_id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_int($candidate) && $candidate > 0) {
                return $candidate;
            }

            if (is_string($candidate) && ctype_digit($candidate)) {
                $value = (int) $candidate;
                if ($value > 0) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function requireUserRole(Request $request): string
    {
        $role = $request->input('role') ?? $request->input('user_role');
        if (is_array($role)) {
            $role = reset($role);
        }

        if (!is_string($role) || trim($role) === '') {
            throw new InvalidArgumentException('A user role is required.');
        }

        return $role;
    }

    private function requireUserId(Request $request): int
    {
        $identifier = $request->input('user_id') ?? $request->input('id') ?? $request->input('target_id');
        if (is_array($identifier)) {
            $identifier = reset($identifier);
        }

        if (is_int($identifier)) {
            if ($identifier > 0) {
                return $identifier;
            }
            throw new InvalidArgumentException('A valid user identifier is required.');
        }

        if (!is_string($identifier) || !ctype_digit($identifier)) {
            throw new InvalidArgumentException('A valid user identifier is required.');
        }

        return (int) $identifier;
    }

    private function parseSuspensionUntil(Request $request): ?DateTimeImmutable
    {
        $value = $request->input('until') ?? $request->input('suspend_until');
        if (is_array($value)) {
            $value = reset($value);
        }

        if ($value === null) {
            return null;
        }

        return SuspendUserCommand::parseUntil(is_string($value) ? $value : (string) $value);
    }

    private function suspensionReason(Request $request): ?string
    {
        $reason = $request->input('reason') ?? $request->input('note');
        if (is_array($reason)) {
            $reason = reset($reason);
        }

        if (!is_string($reason)) {
            return null;
        }

        $reason = trim($reason);

        return $reason === '' ? null : $reason;
    }
}
