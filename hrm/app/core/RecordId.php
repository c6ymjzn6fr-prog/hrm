<?php
declare(strict_types=1);

namespace App\Core;

final class RecordId
{
    public static function make(string $prefix): string
    {
        return $prefix . strtoupper(bin2hex(random_bytes(4)));
    }
}
