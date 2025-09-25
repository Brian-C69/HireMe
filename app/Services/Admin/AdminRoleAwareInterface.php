<?php

declare(strict_types=1);

namespace App\Services\Admin;

interface AdminRoleAwareInterface
{
    public function setAdminRoles(AdminGuardianInterface $guardian, AdminArbiterInterface $arbiter): void;
}
