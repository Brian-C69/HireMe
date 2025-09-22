<?php
declare(strict_types=1);

namespace App\Auth\Providers;

use App\Auth\UserProviderInterface;
use PDO;

final class CandidateProvider implements UserProviderInterface
{
    public function getRole(): string { return 'Candidate'; }

    public function findByEmail(PDO $pdo, string $email): ?array
    {
        $st = $pdo->prepare("SELECT candidate_id AS id, email, password_hash FROM candidates WHERE email=:e LIMIT 1");
        $st->execute([':e' => $email]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public function fetchMeta(PDO $pdo, int $id): array
    {
        $st = $pdo->prepare("SELECT full_name, premium_badge, verified_status FROM candidates WHERE candidate_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'name' => $r['full_name'] ?? '',
            'premium_badge' => (int)($r['premium_badge'] ?? 0),
            'verified_status' => (int)($r['verified_status'] ?? 0),
        ];
    }

    public function create(PDO $pdo, array $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $st = $pdo->prepare(
            "INSERT INTO candidates (full_name,email,password_hash,phone_number,country,created_at,updated_at) 
             VALUES (:n,:e,:p,:ph,:c,:ca,:ua)"
        );
        return $st->execute([
            ':n' => $data['full_name'],
            ':e' => $data['email'],
            ':p' => $data['password_hash'],
            ':ph' => $data['phone'] ?? null,
            ':c' => $data['country'] ?? 'Malaysia',
            ':ca' => $now,
            ':ua' => $now,
        ]);
    }

    public function updatePassword(PDO $pdo, string $email, string $newHash): bool
    {
        $q = $pdo->prepare("UPDATE candidates SET password_hash=:p, updated_at=NOW() WHERE email=:e LIMIT 1");
        return $q->execute([':p' => $newHash, ':e' => $email]);
    }
}
