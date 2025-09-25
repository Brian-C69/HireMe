<?php

declare(strict_types=1);

namespace App\Services\Admin;

final class AdminRoleDefaults
{
    private static ?DefaultAdminGuardian $guardian = null;
    private static ?DefaultAdminArbiter $arbiter = null;

    public static function guardian(): DefaultAdminGuardian
    {
        if (self::$guardian === null) {
            self::$guardian = new DefaultAdminGuardian();
        }

        return self::$guardian;
    }

    public static function arbiter(): DefaultAdminArbiter
    {
        if (self::$arbiter === null) {
            self::$arbiter = new DefaultAdminArbiter();
        }

        return self::$arbiter;
    }
}

trait AdminRoleAwareTrait
{
    private ?AdminGuardianInterface $adminGuardian = null;
    private ?AdminArbiterInterface $adminArbiter = null;

    public function setAdminRoles(AdminGuardianInterface $guardian, AdminArbiterInterface $arbiter): void
    {
        $this->adminGuardian = $guardian;
        $this->adminArbiter = $arbiter;
    }

    protected function adminGuardian(): AdminGuardianInterface
    {
        if ($this->adminGuardian === null) {
            $this->adminGuardian = AdminRoleDefaults::guardian();
        }

        return $this->adminGuardian;
    }

    protected function adminArbiter(): AdminArbiterInterface
    {
        if ($this->adminArbiter === null) {
            $this->adminArbiter = AdminRoleDefaults::arbiter();
        }

        return $this->adminArbiter;
    }
}
