<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Admin;
use App\Models\Candidate;
use App\Models\Employer;
use App\Models\Recruiter;

final class AccountController
{
    /**
     * Show an account as JSON.
     *
     * @param array{id?:string|int} $params
     */
    public function apiShow(array $params): void
    {
        $type = isset($params['type']) ? (string) $params['type'] : (string) ($_GET['type'] ?? '');
        $modelClass = $this->resolveModel($type);

        header('Content-Type: application/json');
        if (!$modelClass) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'type not supported',
                'data'    => null,
            ]);
            return;
        }

        $id   = isset($params['id']) ? (int) $params['id'] : 0;
        $user = $modelClass::find($id);

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
        $type = isset($params['type']) ? (string) $params['type'] : (string) ($_GET['type'] ?? '');
        $modelClass = $this->resolveModel($type);

        header('Content-Type: application/json');
        if (!$modelClass) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'type not supported',
                'data'    => null,
            ]);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $email    = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $role     = trim((string) ($input['role'] ?? ''));

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email required';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        if ($modelClass === Admin::class && $role === '') {
            $errors['role'] = 'Role is required';
        }

        if ($errors) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Validation failed',
                'data'    => $errors,
            ]);
            return;
        }

        $data = [
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ];

        if ($modelClass === Admin::class) {
            $data['role'] = $role;
        }

        $user = $modelClass::create($data);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Account created',
            'data'    => $user->toArray(),
        ]);
    }

    /**
     * Resolve the model class for a given account type.
     */
    private function resolveModel(string $type): ?string
    {
        $map = [
            'admin'     => Admin::class,
            'candidate' => Candidate::class,
            'employer'  => Employer::class,
            'recruiter' => Recruiter::class,
        ];

        $key = strtolower(trim($type));

        return $map[$key] ?? null;
    }
}
