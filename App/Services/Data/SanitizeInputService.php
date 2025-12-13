<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Very simple sanitiser; can be extended later.
 */
class SanitizeInputService
{
    /**
     * Recursively sanitise an array of input values.
     */
    public function sanitizeArray(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $clean[$key] = $this->sanitizeArray($value);
            } else {
                // Basic: trim + strip tags.
                // You can add more rules later.
                $clean[$key] = trim(
                    (string) strip_tags((string) $value)
                );
            }
        }

        return $clean;
    }
}
