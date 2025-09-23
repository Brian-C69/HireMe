<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\DB;
use App\Controllers\Auth\UserProviderFactory;

final class ProfileApiController
{
    public function show(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $role = isset($_GET['role']) ? trim((string)$_GET['role']) : '';

        header('Content-Type: application/json');

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid id']);
            exit;
        }

        $pdo = DB::conn();

        if ($role !== '') {
            $provider = UserProviderFactory::providerForRole(ucfirst(strtolower($role)));
            if ($provider === null) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Unknown role']);
                exit;
            }
            $meta = $provider->fetchMeta($pdo, $id);
            echo json_encode(['status' => 'success', 'profile' => $meta]);
            exit;
        }

        foreach (UserProviderFactory::providers() as $provider) {
            try {
                $meta = $provider->fetchMeta($pdo, $id);
                if (!empty($meta)) {
                    echo json_encode(['status' => 'success', 'profile' => $meta]);
                    exit;
                }
            } catch (\Throwable $e) {
                // ignore and try next provider
            }
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Profile not found']);
    }
}
