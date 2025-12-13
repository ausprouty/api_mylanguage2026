<?php

namespace App\Services\Security;

class SanitizeInputService
{
    /**
     * Recursively sanitize input arrays/strings.
     * - trims strings
     * - removes ASCII control chars (except \t \n \r)
     * - leaves numbers/bools/null as-is
     */
    public static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $cleanKey = is_string($k) ? self::sanitizeString($k) : $k;
                $out[$cleanKey] = self::sanitize($v);
            }
            return $out;
        }

        if (is_string($value)) {
            return self::sanitizeString($value);
        }

        return $value;
    }

    private static function sanitizeString(string $s): string
    {
        $s = trim($s);

        // Remove ASCII control chars except tab/newline/CR
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);

        return $s ?? '';
    }
}
