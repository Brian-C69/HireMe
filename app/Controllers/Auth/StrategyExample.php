<?php
declare(strict_types=1);

namespace App\Auth;

use PDO;

/**
 * Quick demo using the real interface & factory in your project.
 * Shows: UserProviderFactory::findByEmail(...) returns provider + user,
 * then fetch role-specific meta with provider->fetchMeta(...)
 */
final class StrategyExample
{
    public static function demo(PDO $pdo, string $email): void
    {
        $found = UserProviderFactory::findByEmail($pdo, $email);
        if (!$found) {
            echo "No user found for {$email}\n";
            return;
        }
        $provider = $found['provider'];
        $user = $found['user'];
        echo "Found user id={$user['id']} role={$user['role']}\n";
        $meta = $provider->fetchMeta($pdo, (int)$user['id']);
        echo "Role-specific name: " . ($meta['name'] ?? '(none)') . "\n";
    }
}
