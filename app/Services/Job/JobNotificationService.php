<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Services\Job\Contracts\JobNotifierInterface;
use App\Services\Notify;

final class JobNotificationService implements JobNotifierInterface
{
    public function jobPublished(int $jobId, array $jobData): void
    {
        // No dedicated notification for job publication yet.
    }

    public function jobUpdated(int $jobId, array $jobData): void
    {
        // Placeholder for future job update notifications.
    }

    public function applicationSubmitted(int $applicationId): void
    {
        Notify::onApplicationCreated($applicationId);
    }
}
