<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Core\DB;

final class AttemptService
{
    private const MAX_ATTEMPTS = 3;
    private const LOCK_MINUTES = 15;

    public function isLockedOut(PDO $pdo, string $email, string $ip): bool
    {
        $st = $pdo->prepare("SELECT attempts,last_attempt_at FROM login_attempts WHERE email=:e AND ip_address=:i LIMIT 1");
        $st->execute([':e' => $email, ':i' => $ip]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        if ((int)$row['attempts'] < self::MAX_ATTEMPTS) return false;
        return (time() - strtotime((string)$row['last_attempt_at'])) < self::LOCK_MINUTES * 60;
    }

    public function attemptCount(PDO $pdo, string $email, string $ip): int
    {
        $st = $pdo->prepare("SELECT attempts FROM login_attempts WHERE email=:e AND ip_address=:i LIMIT 1");
        $st->execute([':e' => $email, ':i' => $ip]);
        return (int)($st->fetchColumn() ?: 0);
    }

    public function recordFailure(PDO $pdo, string $email, string $ip): void
    {
        $st = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, attempts, last_attempt_at) VALUES (:e,:i,1,NOW()) ON DUPLICATE KEY UPDATE attempts=attempts+1,last_attempt_at=NOW()");
        $st->execute([':e' => $email, ':i' => $ip]);
    }

    public function resetAttempts(PDO $pdo, string $email, string $ip): void
    {
        $st = $pdo->prepare("DELETE FROM login_attempts WHERE email=:e AND ip_address=:i");
        $st->execute([':e' => $email, ':i' => $ip]);
    }
}
