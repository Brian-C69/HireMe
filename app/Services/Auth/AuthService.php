<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Throwable;

/**
 * Lightweight API authentication helper for bearer token workflows.
 *
 * The service intentionally keeps its responsibilities small: it can
 * look up users by token, issue new tokens and revoke existing ones.
 * Tokens are stored using a SHA-256 hash to avoid persisting the raw
 * secret in the database. When reading an incoming token we transparently
 * check both the hashed and plain forms so that existing datasets using
 * unhashed tokens continue to function.
 */
final class AuthService
{
    /**
     * Attempt to resolve a user model from a bearer token.
     */
    public function userByToken(?string $token): ?User
    {
        $normalised = $this->normaliseToken($token);
        if ($normalised === null) {
            return null;
        }

        // Prefer hashed storage but gracefully fall back to plain tokens.
        $hashed = $this->hashToken($normalised);
        $user = $this->findUserByStoredToken($hashed);
        if ($user !== null) {
            return $user;
        }

        return $this->findUserByStoredToken($normalised);
    }

    /**
     * Generate and persist a fresh API token for the given user.
     *
     * @return string|null The plain token value when persistence succeeds.
     */
    public function issueToken(User $user): ?string
    {
        $token = $this->generateToken();
        if ($token === null) {
            return null;
        }

        if (!$this->persistToken($user, $this->hashToken($token))) {
            return null;
        }

        return $token;
    }

    /**
     * Remove any stored API token for the supplied user.
     */
    public function revokeToken(User $user): bool
    {
        return $this->persistToken($user, null);
    }

    private function findUserByStoredToken(string $storedToken): ?User
    {
        try {
            $user = User::query()->where('api_token', $storedToken)->first();
        } catch (Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    private function persistToken(User $user, ?string $storedValue): bool
    {
        try {
            $user->setAttribute('api_token', $storedValue);

            return $user->save();
        } catch (Throwable) {
            return false;
        }
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function normaliseToken(?string $token): ?string
    {
        if (!is_string($token)) {
            return null;
        }

        $trimmed = trim($token);

        return $trimmed === '' ? null : $trimmed;
    }

    private function generateToken(): ?string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable) {
            return null;
        }
    }
}
