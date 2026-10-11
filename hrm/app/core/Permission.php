<?php
declare(strict_types=1);

namespace App\Core;

final class Permission
{
    public static function allows(string $module, string $action): bool
    {
        return Auth::can($module, $action);
    }

    public static function require(string $module, string $action): void
    {
        Auth::requirePermission($module, $action);
    }
}
