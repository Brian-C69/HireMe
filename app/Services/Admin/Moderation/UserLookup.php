<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

use App\Models\Admin;
use App\Models\Candidate;
use App\Models\Employer;
use App\Models\Recruiter;
use Illuminate\Database\Eloquent\Model;

final class UserLookup
{
    /** @var array<string, class-string<Model>> */
    private const ROLE_MODELS = [
        'candidate' => Candidate::class,
        'candidates' => Candidate::class,
        'employer' => Employer::class,
        'employers' => Employer::class,
        'recruiter' => Recruiter::class,
        'recruiters' => Recruiter::class,
        'admin' => Admin::class,
        'admins' => Admin::class,
    ];

    public function find(string $role, int $userId): ?Model
    {
        $normalized = strtolower($role);
        if (!isset(self::ROLE_MODELS[$normalized])) {
            return null;
        }

        $model = self::ROLE_MODELS[$normalized];

        return $model::query()->find($userId);
    }
}
