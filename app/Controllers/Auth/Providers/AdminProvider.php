<?php
declare(strict_types=1);

namespace App\Controllers\Auth\Providers;

use App\Controllers\Auth\UserProviderInterface;
use PDO;

final class AdminProvider implements UserProviderInterface
{
    public function getRole(): string
    {
        return 'Admin';
    }

    public function findByEmail(PDO $pdo, string $email): ?array
    {
        $st = $pdo->prepare('SELECT admin_id AS id, email, password_hash FROM admins WHERE email=:e LIMIT 1');
        $st->execute([':e' => $email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function fetchMeta(PDO $pdo, int $id): array
    {
        $st = $pdo->prepare('SELECT full_name, role, status FROM admins WHERE admin_id=:id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'name' => $row['full_name'] ?? '',
            'premium_badge' => 0,
            'verified_status' => 0,
            'role' => $row['role'] ?? null,
            'status' => $row['status'] ?? null,
        ];
    }

    public function create(PDO $pdo, array $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $st = $pdo->prepare(
            'INSERT INTO admins (full_name, email, password_hash, role, status, created_at, updated_at)'
            . ' VALUES (:n, :e, :p, :r, :s, :c, :u)'
        );

        return $st->execute([
            ':n' => $data['full_name'] ?? '',
            ':e' => $data['email'],
            ':p' => $data['password_hash'],
            ':r' => $data['role'] ?? 'Support',
            ':s' => $data['status'] ?? 'Active',
            ':c' => $now,
            ':u' => $now,
        ]);
    }

    public function updatePassword(PDO $pdo, string $email, string $newHash): bool
    {
        $q = $pdo->prepare('UPDATE admins SET password_hash=:p, updated_at=NOW() WHERE email=:e LIMIT 1');

        return $q->execute([':p' => $newHash, ':e' => $email]);
    }
}
