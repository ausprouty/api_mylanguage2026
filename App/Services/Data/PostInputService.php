<?php

declare(strict_types=1);

namespace App\Services\Data;

use App\Services\Security\SanitizeInputService;

/**
 * Service responsible for reading and sanitizing POST-like input.
 *
 * - Detects JSON vs form-encoded.
 * - Reads php://input for JSON.
 * - Delegates sanitisation to SanitizeInputService.
 */
class PostInputService
{
    public function __construct(
        private SanitizeInputService $sanitizer
    ) {
    }

    /**
     * Return a sanitized associative array representing the request body.
     */
    public function getDataSet(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $contentType = strtolower(trim(explode(';', $contentType)[0]));

        if ($contentType === 'application/json') {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE &&
                is_array($decoded)) {
                return $this->sanitizer->sanitizeArray($decoded);
            }

            // If JSON is invalid, fall back to empty array
            return [];
        }

        // Default: treat as form-encoded POST
        $post = $_POST ?? [];

        return $this->sanitizer->sanitizeArray($post);
    }
}
