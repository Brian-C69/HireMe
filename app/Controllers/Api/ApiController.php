<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security\RateLimiter;
use App\Models\User;
use App\Services\Auth\AuthService;

abstract class ApiController extends Controller
{
    private int $maxAttempts;
    private int $decaySeconds;

    /** @var array{allowed:bool, remaining:int, retry_after:int, limit:int}|null */
    private ?array $currentRateLimit = null;

    public function __construct(protected ?AuthService $auth = null)
    {
        $this->maxAttempts = max(1, (int) ($_ENV['ADMIN_API_RATE_LIMIT'] ?? 120));
        $this->decaySeconds = max(1, (int) ($_ENV['ADMIN_API_RATE_DECAY'] ?? 60));
    }

    protected function success(array $data = [], int $status = 200, array $meta = []): Response
    {
        $payload = ['data' => $data];
        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        $response = Response::json($payload, $status);

        return $this->applyRateLimitHeaders($response);
    }

    protected function error(string $message, int $status = 400, array $meta = []): Response
    {
        $response = Response::json(['errors' => [['detail' => $message, 'meta' => $meta]]], $status);

        return $this->applyRateLimitHeaders($response);
    }

    protected function userFromToken(Request $request): ?User
    {
        if (!$this->auth) {
            return null;
        }

        $token = $request->bearerToken();
        return $this->auth->userByToken($token);
    }

    protected function throttle(Request $request, string $ability = 'default'): ?Response
    {
        $identifier = $request->ip() ?? 'cli';
        if (isset($_SESSION['user']['id'])) {
            $identifier = 'uid:' . (int) $_SESSION['user']['id'];
        } elseif (($token = $request->bearerToken()) !== null) {
            $identifier = 'token:' . substr(hash('sha256', $token), 0, 16);
        }

        $key = implode('|', ['admin-api', $ability, $identifier]);
        $result = RateLimiter::hit($key, $this->maxAttempts, $this->decaySeconds);

        $this->currentRateLimit = [
            'allowed' => $result['allowed'],
            'remaining' => $result['remaining'],
            'retry_after' => $result['retry_after'],
            'limit' => $this->maxAttempts,
        ];

        if ($result['allowed'] === false) {
            return $this->error(
                'Too many requests. Please slow down.',
                429,
                [
                    'retry_after' => $result['retry_after'],
                    'limit' => $this->maxAttempts,
                ]
            );
        }

        return null;
    }

    private function applyRateLimitHeaders(Response $response): Response
    {
        if ($this->currentRateLimit === null) {
            return $response;
        }

        $info = $this->currentRateLimit;
        $this->currentRateLimit = null;

        $response->header('X-RateLimit-Limit', (string) $info['limit']);
        $response->header('X-RateLimit-Remaining', (string) max(0, $info['remaining']));
        $response->header('X-RateLimit-Reset', (string) (time() + $info['retry_after']));

        if ($info['allowed'] === false) {
            $response->header('Retry-After', (string) $info['retry_after']);
        }

        return $response;
    }
}
