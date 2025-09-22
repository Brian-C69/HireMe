<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation\Commands;

use App\Services\Admin\Moderation\ModerationCommand;
use App\Services\Admin\Moderation\ModerationCommandResult;
use App\Services\Admin\Moderation\ModerationSuspensionStore;
use App\Services\Admin\Moderation\RequiresModerationAuthorization;
use App\Services\Admin\Moderation\UserLookup;
use InvalidArgumentException;

final class ReinstateUserCommand implements ModerationCommand, RequiresModerationAuthorization
{
    public function __construct(
        private readonly string $role,
        private readonly int $userId,
        private readonly ModerationSuspensionStore $store,
        private readonly UserLookup $lookup,
        private readonly ?int $moderatorId = null
    ) {
    }

    public function name(): string
    {
        return 'reinstate-user';
    }

    public function execute(): ModerationCommandResult
    {
        $user = $this->lookup->find($this->role, $this->userId);
        if ($user === null) {
            throw new InvalidArgumentException('User record not found.');
        }

        $previous = $this->store->reinstate($this->role, $this->userId);

        return new ModerationCommandResult(
            $this->name(),
            'success',
            [
                'user' => [
                    'role' => strtolower($this->role),
                    'id' => $this->userId,
                    'previous_suspension' => $previous,
                    'moderator_id' => $this->moderatorId,
                ],
                'user_summary' => $user->toArray(),
            ],
            'User reinstated.'
        );
    }
}
