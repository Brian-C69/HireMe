<?php

declare(strict_types=1);

namespace App\Services\Modules;

interface RegistryAwareInterface
{
    public function setRegistry(ModuleRegistry $registry): void;
}
