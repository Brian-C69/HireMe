<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;

final class AccountController
{
    /**
     * Show an account as JSON.
     *
     * @param array{id?:string|int} $params
     */
    public function apiShow(array $params): void
    {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        $user = User::find($id);

        header('Content-Type: application/json');
        if (!$user) {
            http_response_code(404);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Account not found',
                'data'    => null,
            ]);
            return;
        }

        echo json_encode([
            'status'  => 'success',
            'message' => 'Account retrieved',
            'data'    => $user->toArray(),
        ]);
    }

    /**
     * Create a new account from JSON request body.
     *
     * @param array $params
     */
    public function apiCreate(array $params = []): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $email    = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $role     = trim((string)($input['role'] ?? ''));

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email required';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        if ($role === '') {
            $errors['role'] = 'Role is required';
        }

        header('Content-Type: application/json');
        if ($errors) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Validation failed',
                'data'    => $errors,
            ]);
            return;
        }

        $user = User::create([
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
        ]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Account created',
            'data'    => $user->toArray(),
        ]);
    }
}
