<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

interface ModerationAuthorizerInterface
{
    public function authorize(ModerationCommand $command): void;
}
