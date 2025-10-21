<?php
declare(strict_types=1);

namespace App\Support;

final class Caster
{
    /**
     * Format seconds as "HH:MM:SS".
     * - Clamps negatives to 0.
     * - Hours can exceed 99 if needed (no hard cap).
     */
    public static function secondsToHhMmSs(int $seconds): string
    {
        $s   = \max(0, $seconds);
        $h   = intdiv($s, 3600);
        $rem = $s % 3600;
        $m   = intdiv($rem, 60);
        $r   = $rem % 60;
        return \sprintf('%d:%02d:%02d', $h, $m, $r);
    }

    /**
     * Format seconds as "MM:SS".
     * - Clamps negatives to 0.
     * - Minutes can exceed 59 (e.g., "125:07").
     */
    public static function secondsToMmSs(int $seconds): string
    {
        $s = \max(0, $seconds);
        $m = intdiv($s, 60);
        $r = $s % 60;
        return \sprintf('%d:%02d', $m, $r);
    }

    /**
     * Smart formatter:
     * - "< 3600"  -> "MM:SS"
     * - ">= 3600" -> "HH:MM:SS"
     */
    public static function secondsToTimecode(int $seconds): string
    {
        $s = \max(0, $seconds);
        return ($s < 3600) ? self::secondsToMmSs($s) : self::secondsToHhMmSs($s);
    }

    /**
     * Flexible bool parser:
     *  - bool -> as-is
     *  - int: 1 => true, others => false
     *  - string: '1','y','t','true','on' => true; '0','','n','f','false','off' => false
     *  - fallback: (bool)$v
     */
    public static function toBool(mixed $v): bool
    {
        if (\is_bool($v)) return $v;
        if (\is_int($v))  return $v === 1;

        if (\is_string($v)) {
            $t = \strtolower(\trim($v));
            if (\in_array($t, ['1','y','t','true','on'], true)) return true;
            if (\in_array($t, ['0','','n','f','false','off'], true)) return false;
        }
        return (bool)$v;
    }

    /** Accept null, '', YYYY-MM-DD, or DateTimeInterface. Otherwise throw. */
    public static function toDateYmdOrNull(mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');

        $s = \trim((string)$v);
        if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

        throw new \InvalidArgumentException('Date must be YYYY-MM-DD, DateTimeInterface, or null.');
    }

    /** int|numeric-string|null -> ?int (non-numeric -> null) */
    public static function toIntOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') return null;
        if (\is_int($v)) return $v;
        return \is_numeric($v) ? (int)$v : null;
    }

    /** Numeric-ish -> int; null/''/non-numeric -> 0. (Keeps sign.) */
    public static function toIntOrZero(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (\is_int($v)) return $v;
        return \is_numeric($v) ? (int)$v : 0;
    }

    /**
     * any -> trimmed LOWERCASE string; null -> ''
     */
    public static function toLowerText(mixed $v): string
    {
        return \strtolower(self::toText($v));
    }

    /**
     * any -> trimmed LOWERCASE string; '' -> null
     */
    public static function toLowerTextOrNull(mixed $v): ?string
    {
        $s = self::toTextOrNull($v);
        return $s === null ? null : \strtolower($s);
    }

    /** Numeric -> int ≥ 0; null/''/non-numeric -> null */
    public static function toNonNegativeIntOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') return null;
        if (\is_int($v)) return $v < 0 ? null : $v;
        if (\is_numeric($v)) {
            $n = (int)$v;
            return $n < 0 ? null : $n;
        }
        return null;
    }

    /** Numeric-ish -> int ≥ 0; null/''/non-numeric -> 0 */
    public static function toNonNegativeIntOrZero(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (\is_int($v)) return \max(0, $v);
        if (\is_numeric($v)) return \max(0, (int)$v);
        return 0;
    }

    /**
     * Same as toSecondsOrZero() but returns null for null/empty/"start".
     */
    public static function toSecondsOrNull(mixed $v): ?int
    {
        if (\is_int($v)) return \max(0, $v);

        $t = self::toTimecodeOrNull($v);
        if ($t === null || $t === '' || $t === '0') return null;
        if (\ctype_digit($t)) return (int)$t;

        $parts = \explode(':', $t);
        $n = \count($parts);
        if ($n === 2) {
            [$m, $s] = \array_map('intval', $parts);
            return \max(0, $m * 60 + $s);
        }
        if ($n === 3) {
            [$h, $m, $s] = \array_map('intval', $parts);
            return \max(0, $h * 3600 + $m * 60 + $s);
        }
        // Unknown string -> null (lenient)
        return null;
    }

    /**
     * Accepts int seconds, "SS", "MM:SS", "HH:MM:SS", "start", "", null.
     * Returns non-negative seconds; unknown/empty -> 0.
     */
    public static function toSecondsOrZero(mixed $v): int
    {
        if (\is_int($v)) return \max(0, $v);

        $t = self::toTimecodeOrNull($v);
        if ($t === null || $t === '' || $t === '0') return 0;
        if (\ctype_digit($t)) return (int)$t;

        $parts = \explode(':', $t);
        $n = \count($parts);
        if ($n === 2) {
            [$m, $s] = \array_map('intval', $parts);
            return \max(0, $m * 60 + $s);
        }
        if ($n === 3) {
            [$h, $m, $s] = \array_map('intval', $parts);
            return \max(0, $h * 3600 + $m * 60 + $s);
        }
        // Unknown string -> 0 (lenient)
        return 0;
    }

    /** any -> trimmed string; null -> '' */
    public static function toText(mixed $v): string
    {
        return \trim((string)($v ?? ''));
    }

    /** any -> trimmed string; '' -> null */
    public static function toTextOrNull(mixed $v): ?string
    {
        if ($v === null) return null;
        $s = \trim((string)$v);
        return $s === '' ? null : $s;
    }

    /**
     * Accepts: int seconds, "SS", "MM:SS", "HH:MM:SS", "start", "null", null, ''.
     * Returns canonical string ("0","SS","MM:SS","HH:MM:SS") or null.
     * Unknown strings are returned unchanged (lenient).
     */
    public static function toTimecodeOrNull(mixed $v): ?string
    {
        if ($v === null) return null;
        if (\is_int($v)) return (string)\max(0, $v);

        $s = \trim((string)$v);
        if ($s === '' || \strtolower($s) === 'null') return null;
        if (\strtolower($s) === 'start') return '0';
        if (\preg_match('/^\d+$/', $s)) return $s; // seconds
        if (\preg_match('/^\d{1,2}:\d{1,2}(:\d{1,2})?$/', $s)) return $s; // MM:SS or HH:MM:SS

        return $s; // lenient passthrough
    }

    /** any -> trimmed UPPERCASE string; null -> '' */
    public static function toUpperText(mixed $v): string
    {
        return \strtoupper(self::toText($v));
    }
}
