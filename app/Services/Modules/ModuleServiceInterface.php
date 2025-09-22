<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;

interface ModuleServiceInterface
{
    /**
     * Unique key used to refer to the module.
     */
    public function name(): string;

    /**
     * Handle an API operation for the module.
     *
     * @param string      $type Operation or resource identifier within the module.
     * @param string|null $id   Optional target identifier or scope hint.
     * @param Request     $request Incoming request data.
     *
     * @return array<string, mixed>
     */
    public function handle(string $type, ?string $id, Request $request): array;
}
