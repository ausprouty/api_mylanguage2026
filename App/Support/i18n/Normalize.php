<?php
declare(strict_types=1);

namespace App\Support\i18n;

final class Normalize
{
    public static function normalizeVariant(?string $v): string
    {
        // coerce to string, trim, collapse empties to 'default'
        $v = trim((string)$v);
        return $v === '' ? 'default' : strtolower($v);
    }
}
