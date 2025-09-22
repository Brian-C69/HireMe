<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

use App\Core\Request;

final class AdminRequestAuthorizer implements ModerationAuthorizerInterface
{
    public function __construct(private readonly Request $request)
    {
    }

    public function authorize(ModerationCommand $command): void
    {
        if (!$command instanceof RequiresModerationAuthorization) {
            return;
        }

        $header = $this->request->header('X-Admin-Moderation');
        if (is_string($header) && $this->isAllowedToken($header)) {
            return;
        }

        $token = $this->request->header('X-Admin-Token');
        if (is_string($token) && $this->isAllowedToken($token)) {
            return;
        }

        throw new ModerationAuthorizationException('Moderation command requires elevated privileges.');
    }

    private function isAllowedToken(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['1', 'true', 'allow', 'allowed', 'yes'], true);
    }
}
