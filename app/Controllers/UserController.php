<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;

final class UserController
{
    public function index(): void
    {
        $users = User::all();
        header('Content-Type: application/json');
        echo $users->toJson();
    }

    public function show(int $id): void
    {
        $user = User::findOrFail($id);
        header('Content-Type: application/json');
        echo $user->toJson();
    }

    public function store(): void
    {
        $data = [
            'email' => (string)($_POST['email'] ?? ''),
            'password_hash' => password_hash((string)($_POST['password'] ?? ''), PASSWORD_DEFAULT),
            'role' => (string)($_POST['role'] ?? ''),
        ];
        $user = User::create($data);
        header('Content-Type: application/json');
        echo $user->toJson();
    }
}

