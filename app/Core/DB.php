<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class DB
{
    private static ?PDO $pdo = null;

    /**
     * Allow tests or services to override the active PDO connection.
     * This enables using in-memory databases during testing while the
     * application continues to retrieve connections via DB::conn().
     */
    public static function setConnection(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function conn(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        $root   = dirname(__DIR__, 2);
        $config = require $root . '/config/config.php';
        $db     = $config['db'] ?? null;
        if (!$db || empty($db['dsn'])) {
            throw new RuntimeException('DB config missing or invalid.');
        }
        self::$pdo = new PDO($db['dsn'], $db['user'] ?? null, $db['pass'] ?? null, $db['options'] ?? []);
        return self::$pdo;
    }
}
