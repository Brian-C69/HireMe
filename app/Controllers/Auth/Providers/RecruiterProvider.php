<?php
declare(strict_types=1);

namespace App\Controllers\Auth\Providers;

use App\Controllers\Auth\UserProviderInterface;
use PDO;

final class RecruiterProvider implements UserProviderInterface
{
    public function getRole(): string { return 'Recruiter'; }

    public function findByEmail(PDO $pdo, string $email): ?array
    {
        $st = $pdo->prepare("SELECT recruiter_id AS id, email, password_hash FROM recruiters WHERE email=:e LIMIT 1");
        $st->execute([':e' => $email]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public function fetchMeta(PDO $pdo, int $id): array
    {
        $st = $pdo->prepare("SELECT full_name FROM recruiters WHERE recruiter_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'name' => $r['full_name'] ?? '',
            'premium_badge' => 0,
            'verified_status' => 0,
        ];
    }

    public function create(PDO $pdo, array $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $st = $pdo->prepare(
            "INSERT INTO recruiters (full_name,email,password_hash,agency_name,contact_number,location,created_at,updated_at) 
             VALUES (:n,:e,:p,:a,:c,:l,:ca,:ua)"
        );
        return $st->execute([
            ':n'  => $data['full_name'],
            ':e'  => $data['email'],
            ':p'  => $data['password_hash'],
            ':a'  => $data['agency_name'] ?? null,
            ':c'  => $data['contact_number'] ?? null,
            ':l'  => $data['location'] ?? null,
            ':ca' => $now,
            ':ua' => $now,
        ]);
    }

    public function updatePassword(PDO $pdo, string $email, string $newHash): bool
    {
        $q = $pdo->prepare("UPDATE recruiters SET password_hash=:p, updated_at=NOW() WHERE email=:e LIMIT 1");
        return $q->execute([':p' => $newHash, ':e' => $email]);
    }
}
