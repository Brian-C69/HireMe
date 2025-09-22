<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

final class AllowAllModerationAuthorizer implements ModerationAuthorizerInterface
{
    public function authorize(ModerationCommand $command): void
    {
        // Intentionally allow all commands. Useful for testing or internal tooling.
    }
}
