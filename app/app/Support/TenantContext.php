<?php

namespace App\Support;

use App\Models\Tenant;

class TenantContext
{
    private static ?Tenant $tenant = null;

    public static function set(?Tenant $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function get(): ?Tenant
    {
        return self::$tenant;
    }

    public static function id(): ?string
    {
        return self::$tenant?->id;
    }

    public static function clear(): void
    {
        self::$tenant = null;
    }
}
