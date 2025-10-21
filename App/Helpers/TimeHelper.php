<?php

namespace App\Helpers;

class TimeHelper
{
    /**
     * Convert time input to seconds.
     * Special cases:
     * - "start" → 0
     * - "end"   → null
     *
     * Supports formats:
     *   - "HH:MM:SS"
     *   - "MM:SS"
     *   - "SS" or integer seconds
     *
     * @param string|int|null $time
     * @return int|null
     */
    public static function convertToSeconds($time): ?int
    {
        if (is_null($time)) {
            return null;
        }

        if (is_string($time)) {
            $time = strtolower(trim($time));

            if ($time === 'start') {
                return 0;
            }

            if ($time === 'end') {
                return null;
            }

            $parts = explode(':', $time);
            $count = count($parts);

            if ($count === 3) {
                [$hours, $minutes, $seconds] = $parts;
                return ((int)$hours * 3600) + ((int)$minutes * 60) + (int)$seconds;
            }

            if ($count === 2) {
                [$minutes, $seconds] = $parts;
                return ((int)$minutes * 60) + (int)$seconds;
            }

            if (ctype_digit($time)) {
                return (int)$time;
            }
        }

        if (is_numeric($time)) {
            return (int)$time;
        }

        return null;
    }
}
