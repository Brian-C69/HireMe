<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

interface ModerationCommand
{
    public function name(): string;

    public function execute(): ModerationCommandResult;
}
