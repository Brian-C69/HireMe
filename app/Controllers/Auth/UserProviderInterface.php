<?php
declare(strict_types=1);

namespace App\Auth;

use PDO;

interface UserProviderInterface
{
    public function getRole(): string;

    //Return row with at least: id, email, password_hash or null if not found.
    public function findByEmail(PDO $pdo, string $email): ?array;

    //Role-specific meta (name, premium_badge, verified_status, ...)
    //Returns associative array.
    public function fetchMeta(PDO $pdo, int $id): array;

    //Create a new user of this provider's type.
    //Input keys are role-dependent.
    public function create(PDO $pdo, array $data): bool;

    //Update user's password by email.
    public function updatePassword(PDO $pdo, string $email, string $newHash): bool;
}
