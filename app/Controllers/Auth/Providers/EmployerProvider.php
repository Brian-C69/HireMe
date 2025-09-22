<?php
declare(strict_types=1);

namespace App\Controllers\Auth\Providers;

use App\Controllers\Auth\UserProviderInterface;
use PDO;

final class EmployerProvider implements UserProviderInterface
{
    public function getRole(): string { return 'Employer'; }

    public function findByEmail(PDO $pdo, string $email): ?array
    {
        $st = $pdo->prepare("SELECT employer_id AS id, email, password_hash FROM employers WHERE email=:e LIMIT 1");
        $st->execute([':e' => $email]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public function fetchMeta(PDO $pdo, int $id): array
    {
        $st = $pdo->prepare("SELECT company_name FROM employers WHERE employer_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'name' => $r['company_name'] ?? '',
            'premium_badge' => 0,
            'verified_status' => 0,
        ];
    }

    public function create(PDO $pdo, array $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $st = $pdo->prepare(
            "INSERT INTO employers (company_name,email,password_hash,industry,location,contact_person_name,contact_number,created_at,updated_at) 
             VALUES (:cn,:e,:p,:i,:l,:cp,:cc,:ca,:ua)"
        );
        return $st->execute([
            ':cn' => $data['company_name'],
            ':e'  => $data['email'],
            ':p'  => $data['password_hash'],
            ':i'  => $data['industry'] ?? null,
            ':l'  => $data['location'] ?? null,
            ':cp' => $data['contact_person_name'] ?? null,
            ':cc' => $data['contact_number'] ?? null,
            ':ca' => $now,
            ':ua' => $now,
        ]);
    }

    public function updatePassword(PDO $pdo, string $email, string $newHash): bool
    {
        $q = $pdo->prepare("UPDATE employers SET password_hash=:p, updated_at=NOW() WHERE email=:e LIMIT 1");
        return $q->execute([':p' => $newHash, ':e' => $email]);
    }
}
