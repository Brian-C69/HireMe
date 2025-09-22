<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation\Commands;

use App\Services\Admin\Moderation\ModerationCommand;
use App\Services\Admin\Moderation\ModerationCommandResult;
use App\Services\Admin\Moderation\ModerationSuspensionStore;
use App\Services\Admin\Moderation\RequiresModerationAuthorization;
use App\Services\Admin\Moderation\UserLookup;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class SuspendUserCommand implements ModerationCommand, RequiresModerationAuthorization
{
    public function __construct(
        private readonly string $role,
        private readonly int $userId,
        private readonly ModerationSuspensionStore $store,
        private readonly UserLookup $lookup,
        private readonly ?DateTimeInterface $until = null,
        private readonly ?string $reason = null,
        private readonly ?int $moderatorId = null
    ) {
    }

    public function name(): string
    {
        return 'suspend-user';
    }

    public function execute(): ModerationCommandResult
    {
        $user = $this->lookup->find($this->role, $this->userId);
        if ($user === null) {
            throw new InvalidArgumentException('User record not found.');
        }

        $record = $this->store->suspend($this->role, $this->userId, $this->until, $this->reason, $this->moderatorId);

        return new ModerationCommandResult(
            $this->name(),
            'success',
            [
                'user' => [
                    'role' => $record['role'],
                    'id' => $record['user_id'],
                    'suspension' => $record,
                ],
                'user_summary' => $user->toArray(),
            ],
            'User suspended.'
        );
    }

    public static function parseUntil(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $exception) {
            throw new InvalidArgumentException('Invalid suspension expiry date.');
        }
    }
}
