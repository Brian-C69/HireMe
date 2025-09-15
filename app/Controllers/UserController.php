<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Admin;
use App\Models\Candidate;
use App\Models\Employer;
use App\Models\Recruiter;

final class UserController
{
    /**
     * Map URL user type segments to model classes.
     *
     * @var array<string, class-string>
     */
    private const MODEL_MAP = [
        'admins'     => Admin::class,
        'candidates' => Candidate::class,
        'employers'  => Employer::class,
        'recruiters' => Recruiter::class,
    ];

    /**
     * Resolve the model class for the given user type.
     */
    private function resolveModel(string $type): ?string
    {
        $key = strtolower($type);
        return self::MODEL_MAP[$key] ?? null;
    }

    /**
     * List all users of a given type.
     *
     * @param array{type?:string} $params
     */
    public function index(array $params = []): void
    {
        $model = $this->resolveModel($params['type'] ?? '');
        header('Content-Type: application/json');
        if ($model === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Unknown user type']);
            return;
        }
        echo $model::all()->toJson();
    }

    /**
     * Show a specific user record.
     *
     * @param array{type?:string,id?:string|int} $params
     */
    public function show(array $params): void
    {
        $model = $this->resolveModel($params['type'] ?? '');
        $id    = isset($params['id']) ? (int)$params['id'] : 0;
        header('Content-Type: application/json');
        if ($model === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Unknown user type']);
            return;
        }
        $user = $model::find($id);
        if ($user === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }
        echo $user->toJson();
    }

    /**
     * Create a new user of a given type from POST data.
     *
     * @param array{type?:string} $params
     */
    public function store(array $params = []): void
    {
        $model = $this->resolveModel($params['type'] ?? '');
        header('Content-Type: application/json');
        if ($model === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Unknown user type']);
            return;
        }
        $data = $_POST;
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash((string)$data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        $user = $model::create($data);
        echo $user->toJson();
    }
}
