<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\DB;
use App\Controllers\Auth\UserProviderFactory;
use App\Services\AttemptService;

final class AuthApiController
{
    private const JWT_SECRET = 'replace_this_with_a_secure_secret';

    private function json($data, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function createJwt(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload['iat'] = time();
        $payload['exp'] = time() + 3600;
        $b64 = static function ($data) {
            return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        };
        $headerB = $b64($header);
        $payloadB = $b64($payload);
        $signature = hash_hmac('sha256', "$headerB.$payloadB", self::JWT_SECRET, true);
        $signatureB = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        return "$headerB.$payloadB.$signatureB";
    }

    private function httpGet(string $url, array $headers = [], int $timeout = 5): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno || $body === false || $httpCode >= 500) {
            return null;
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $roleHint = isset($input['role']) ? trim((string)$input['role']) : null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['status' => 'error', 'message' => 'Invalid email'], 400);
        }
        if ($password === '') {
            $this->json(['status' => 'error', 'message' => 'Password required'], 400);
        }

        $pdo = DB::conn();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $attemptSvc = new AttemptService();

        if ($attemptSvc->isLockedOut($pdo, $email, $ip)) {
            $this->json([
                'status' => 'error',
                'message' => 'Account temporarily locked due to multiple failed attempts.',
            ], 429);
        }

        $found = null;
        if ($roleHint !== null && $roleHint !== '') {
            $provider = UserProviderFactory::providerForRole(ucfirst(strtolower($roleHint)));
            if ($provider) {
                $row = $provider->findByEmail($pdo, $email);
                if ($row !== null) {
                    $row['role'] = $provider->getRole();
                    $found = ['provider' => $provider, 'user' => $row];
                }
            }
        }

        if (!$found) {
            $found = UserProviderFactory::findByEmail($pdo, $email);
        }

        if (!$found || !password_verify($password, (string)$found['user']['password_hash'])) {
            $attemptSvc->recordFailure($pdo, $email, $ip);
            $left = max(0, AttemptService::MAX_ATTEMPTS - $attemptSvc->attemptCount($pdo, $email, $ip));
            $this->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
                'attempts_left' => $left,
            ], 401);
        }

        $provider = $found['provider'];
        $user = $found['user'];
        $meta = $provider->fetchMeta($pdo, (int)$user['id']);

        $attemptSvc->resetAttempts($pdo, $email, $ip);

        $payload = [
            'sub' => (int)$user['id'],
            'email' => (string)$user['email'],
            'role' => (string)$user['role'],
        ];
        $jwt = $this->createJwt($payload);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $profilePath = preg_replace('#/auth/login\.php$#', '/profile/show.php', $scriptName);
        if ($profilePath === $scriptName || $profilePath === '') {
            $base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
            $profilePath = ($base !== '' ? $base : '') . '/api/profile/show.php';
        }
        $profileUrl = $scheme . '://' . $host . rtrim($profilePath, '/');
        $profileUrl .= '?id=' . urlencode((string)$user['id']);
        $profileUrl .= '&role=' . urlencode(strtolower((string)$user['role']));

        $profileResp = $this->httpGet($profileUrl, ['Accept: application/json']);
        $profile = null;
        if (is_array($profileResp)) {
            $profile = $profileResp['profile'] ?? ($profileResp['user'] ?? null);
        }

        $this->json([
            'status' => 'success',
            'token' => $jwt,
            'role' => (string)$user['role'],
            'user_id' => (int)$user['id'],
            'profile' => $profile ?? $meta,
        ]);
    }
}
