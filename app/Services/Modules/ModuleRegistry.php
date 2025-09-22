<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use InvalidArgumentException;

final class ModuleRegistry
{
    /** @var array<string, ModuleServiceInterface> */
    private array $services = [];

    /** @var array<string, string> */
    private array $aliases = [];

    public function register(ModuleServiceInterface $service, array $aliases = []): void
    {
        $key = strtolower($service->name());
        $this->services[$key] = $service;

        if ($service instanceof RegistryAwareInterface) {
            $service->setRegistry($this);
        }

        $this->aliases[$key] = $key;
        foreach ($aliases as $alias) {
            $this->aliases[strtolower($alias)] = $key;
        }
    }

    public function get(string $name): ?ModuleServiceInterface
    {
        $key = strtolower($name);
        if (isset($this->services[$key])) {
            return $this->services[$key];
        }

        if (isset($this->aliases[$key])) {
            $canonical = $this->aliases[$key];
            return $this->services[$canonical] ?? null;
        }

        return null;
    }

    /**
     * Invoke another module within the same process.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function call(
        string $module,
        string $type,
        ?string $id = null,
        array $query = [],
        array $body = [],
        string $method = 'GET'
    ): array {
        $service = $this->get($module);
        if ($service === null) {
            throw new InvalidArgumentException(sprintf('Unknown module "%s".', $module));
        }

        $request = $this->makeRequest($query, $body, $method, $module, $type, $id);

        return $service->handle($type, $id, $request);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function makeRequest(
        array $query = [],
        array $body = [],
        string $method = 'GET',
        ?string $module = null,
        ?string $type = null,
        ?string $id = null
    ): Request {
        $method = strtoupper($method);
        $uri = '/internal';
        if ($module !== null) {
            $segments = array_filter([$module, $type, $id], static fn ($segment) => $segment !== null && $segment !== '');
            $uri = '/internal/' . implode('/', array_map(static fn ($segment) => (string) $segment, $segments));
        }

        $server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($method !== 'GET') {
            $server['CONTENT_TYPE'] = 'application/json';
        }

        return new Request($query, $body, [], [], $server);
    }

    public static function boot(): self
    {
        $registry = new self();

        $registry->register(new UserManagementService(), ['user', 'users', 'auth', 'authentication']);
        $registry->register(new ResumeProfileService(), ['resume', 'resumes', 'profile', 'profiles']);
        $registry->register(new JobApplicationService(), ['job', 'jobs', 'application', 'applications']);
        $registry->register(new PaymentBillingService(), ['payment', 'payments', 'billing']);
        $registry->register(new AdminModerationService(), ['admin', 'admins', 'moderation', 'moderator']);

        return $registry;
    }
}
