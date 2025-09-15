<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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

        $profileService = (string) getenv('PROFILE_SERVICE_URL');
        if ($profileService === '') {
            $profileService = 'http://profile-service';
        }

        $profile = null;

        try {
            $client = new Client([
                'base_uri' => rtrim($profileService, '/') . '/',
                'timeout' => 2.0,
                'http_errors' => false,
            ]);

            $response = $client->get("api/profiles/{$id}");
            if ($response->getStatusCode() === 200) {
                $decoded = json_decode((string) $response->getBody(), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $profile = $decoded;
                }
            }
        } catch (GuzzleException $e) {
            // Silently ignore network errors and timeouts
        }

        $data = $user->toArray();
        $data['profile'] = $profile;

        header('Content-Type: application/json');
        echo json_encode($data);
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

