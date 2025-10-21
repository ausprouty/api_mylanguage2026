<?php

namespace App\Helpers;

use App\Services\LoggerService;
use App\Responses\ResponseBuilder;

class ControllerValidator
{
    /**
     * Validates and casts route arguments.
     *
     * @param array $args      Raw input arguments (typically from routing).
     * @param array $required  List of required keys.
     * @param array $optional  List of optional keys.
     * @param array $casts     Type casts for individual keys (e.g. ['lesson' => 'int']).
     * @return array|null      Returns casted values or null if validation fails.
     */
    public static function validateArgs(array $args, array $required = [], array $optional = [], array $casts = []): ?array
    {
        foreach ($required as $key) {
            if (!isset($args[$key])) {
                LoggerService::logInfo('MissingArgs', print_r($args, true));
                ResponseBuilder::error("Missing required argument: $key")
                    ->json(); // outputs and exits
                return null; // unreachable, but keeps method signature safe
            }
        }

        $validated = [];

        foreach ($required as $key) {
            $validated[$key] = self::applyCast($args[$key], $casts[$key] ?? null);
        }

        foreach ($optional as $key) {
            $validated[$key] = isset($args[$key])
                ? self::applyCast($args[$key], $casts[$key] ?? null)
                : null;
        }

        return $validated;
    }

    private static function applyCast($value, ?string $cast)
    {
        return match ($cast) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}
