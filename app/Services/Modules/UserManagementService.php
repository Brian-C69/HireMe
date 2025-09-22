<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use App\Models\Admin;
use App\Models\Candidate;
use App\Models\Employer;
use App\Models\Recruiter;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class UserManagementService extends AbstractModuleService
{
    /** @var array<string, class-string<Model>> */
    private const ROLE_MODELS = [
        'candidates' => Candidate::class,
        'employers' => Employer::class,
        'recruiters' => Recruiter::class,
        'admins' => Admin::class,
    ];

    /** @var array<string, string> */
    private const ROLE_ALIASES = [
        'candidate' => 'candidates',
        'candidates' => 'candidates',
        'talent' => 'candidates',
        'employer' => 'employers',
        'employers' => 'employers',
        'company' => 'employers',
        'recruiter' => 'recruiters',
        'recruiters' => 'recruiters',
        'admin' => 'admins',
        'admins' => 'admins',
    ];

    public function name(): string
    {
        return 'user-management';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        return match (strtolower($type)) {
            'users' => $this->listUsers($request, $id),
            'user' => $this->showUser($request, $id),
            'authenticate', 'auth', 'login' => $this->authenticateUser($request),
            default => throw new InvalidArgumentException(sprintf('Unknown user management operation "%s".', $type)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function listUsers(Request $request, ?string $scope): array
    {
        $roleHint = $scope !== null && $scope !== '' ? $scope : ($this->query($request, 'role') ?? 'all');
        $role = $this->normaliseRole($roleHint);

        if ($role === null || $role === 'all') {
            $payload = [];
            $counts = [];
            $total = 0;
            foreach (self::ROLE_MODELS as $roleKey => $model) {
                $records = $model::query()->orderBy($model::CREATED_AT ?? 'created_at', 'desc')->get();
                $payload[$roleKey] = $records->map(static fn (Model $record) => $record->toArray())->all();
                $counts[$roleKey] = $records->count();
                $total += $counts[$roleKey];
            }

            $counts['total'] = $total;

            return $this->respond([
                'role' => 'all',
                'users' => $payload,
                'counts' => $counts,
            ]);
        }

        $modelClass = $this->modelForRole($role);
        if ($modelClass === null) {
            throw new InvalidArgumentException(sprintf('Unknown user role "%s".', (string) $roleHint));
        }

        $collection = $modelClass::query()->orderBy($modelClass::CREATED_AT ?? 'created_at', 'desc')->get();

        return $this->respond([
            'role' => $role,
            'users' => $collection->map(static fn (Model $record) => $record->toArray())->all(),
            'count' => $collection->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function showUser(Request $request, ?string $id): array
    {
        $userId = $this->requireIntId($id, 'A numeric user identifier is required.');
        $roleHint = $this->query($request, 'role');

        if ($roleHint !== null) {
            $role = $this->normaliseRole($roleHint);
            if ($role === null) {
                throw new InvalidArgumentException(sprintf('Unknown user role "%s".', $roleHint));
            }

            $modelClass = $this->modelForRole($role);
            $user = $modelClass::find($userId);
            if ($user === null) {
                throw new InvalidArgumentException('User record not found.');
            }

            return $this->respond([
                'role' => $role,
                'user' => $user->toArray(),
            ]);
        }

        foreach (self::ROLE_MODELS as $role => $modelClass) {
            $user = $modelClass::find($userId);
            if ($user !== null) {
                return $this->respond([
                    'role' => $role,
                    'user' => $user->toArray(),
                ]);
            }
        }

        throw new InvalidArgumentException('User record not found.');
    }

    /**
     * @return array<string, mixed>
     */
    private function authenticateUser(Request $request): array
    {
        $email = $this->query($request, 'email', null) ?? (string) $request->input('email', '');
        $password = $this->query($request, 'password', null) ?? (string) $request->input('password', '');
        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('Email and password are required for authentication.');
        }

        $roleHint = $this->query($request, 'role') ?? (string) $request->input('role', '');
        $rolesToCheck = [];
        if ($roleHint !== '') {
            $role = $this->normaliseRole($roleHint);
            if ($role === null) {
                throw new InvalidArgumentException(sprintf('Unknown user role "%s".', $roleHint));
            }
            $rolesToCheck[] = $role;
        } else {
            $rolesToCheck = array_keys(self::ROLE_MODELS);
        }

        foreach ($rolesToCheck as $role) {
            $modelClass = $this->modelForRole($role);
            if ($modelClass === null) {
                continue;
            }

            /** @var Model|null $user */
            $user = $modelClass::query()->where('email', $email)->first();
            if ($user === null) {
                continue;
            }

            $data = $user->toArray();
            $hash = $data['password_hash'] ?? null;
            if (is_string($hash) && $hash !== '' && password_verify($password, $hash)) {
                unset($data['password_hash']);

                return $this->respond([
                    'authenticated' => true,
                    'role' => $role,
                    'user' => $data,
                ]);
            }
        }

        return $this->respond([
            'authenticated' => false,
            'message' => 'Invalid credentials provided.',
        ]);
    }

    private function normaliseRole(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }

        $key = strtolower(trim($role));
        if ($key === 'all') {
            return 'all';
        }

        if (isset(self::ROLE_ALIASES[$key])) {
            return self::ROLE_ALIASES[$key];
        }

        return isset(self::ROLE_MODELS[$key]) ? $key : null;
    }

    /**
     * @return class-string<Model>|null
     */
    private function modelForRole(string $role): ?string
    {
        $key = $this->normaliseRole($role);
        return $key !== null && $key !== 'all' ? (self::ROLE_MODELS[$key] ?? null) : null;
    }
}
