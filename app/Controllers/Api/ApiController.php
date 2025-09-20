<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\Auth\AuthService;

abstract class ApiController extends Controller
{
    public function __construct(protected ?AuthService $auth = null)
    {
    }

    protected function success(array $data = [], int $status = 200, array $meta = []): Response
    {
        $payload = ['data' => $data];
        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return Response::json($payload, $status);
    }

    protected function error(string $message, int $status = 400, array $meta = []): Response
    {
        return Response::json(['errors' => [['detail' => $message, 'meta' => $meta]]], $status);
    }

    protected function userFromToken(Request $request): ?User
    {
        if (!$this->auth) {
            return null;
        }

        $token = $request->bearerToken();
        return $this->auth->userByToken($token);
    }
}
