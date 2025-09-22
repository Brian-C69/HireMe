<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Job\JobModuleFacade;

/**
 * Backwards-compatible wrapper that delegates job orchestration to the module facade.
 */
final class JobService
{
    private JobModuleFacade $facade;

    public function __construct(?JobModuleFacade $facade = null)
    {
        $this->facade = $facade ?? JobModuleFacade::buildDefault();
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    public function create(string $role, int $userId, array $input): array
    {
        return $this->facade->publishJob($role, $userId, $input);
    }

    /**
     * @return array{0:array<string, mixed>,1:array<string,string>}
     */
    public function validate(string $role, int $userId, array $input): array
    {
        return $this->facade->validateJobInput($role, $userId, $input);
    }
}
