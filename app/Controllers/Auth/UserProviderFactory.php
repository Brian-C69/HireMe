<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\Auth\Providers\CandidateProvider;
use App\Controllers\Auth\Providers\EmployerProvider;
use App\Controllers\Auth\Providers\RecruiterProvider;
use PDO;

final class UserProviderFactory
{
    //Return provider instances (order matters for email lookup).
    //Add or remove providers here when your roles change.
    //@return UserProviderInterface[]
    public static function providers(): array
    {
        return [
            new CandidateProvider(),
            new EmployerProvider(),
            new RecruiterProvider(),
        ];
    }

    public static function providerForRole(string $role): ?UserProviderInterface
    {
        foreach (self::providers() as $p) {
            if ($p->getRole() === $role) return $p;
        }
        return null;
    }

    //Look up a user by email across providers.
    //Returns ['provider'=>UserProviderInterface, 'user'=>row] or null.
    public static function findByEmail(PDO $pdo, string $email): ?array
    {
        foreach (self::providers() as $p) {
            $row = $p->findByEmail($pdo, $email);
            if ($row !== null) {
                $row['role'] = $p->getRole();
                return ['provider' => $p, 'user' => $row];
            }
        }
        return null;
    }

    public static function emailExistsAny(PDO $pdo, string $email): bool
    {
        return (bool)self::findByEmail($pdo, $email);
    }
}
