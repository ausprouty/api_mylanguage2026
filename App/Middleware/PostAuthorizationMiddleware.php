<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Security\SanitizeInputService;
use App\Services\Data\PostInputService;
use App\Services\Security\PostAuthorizationService;

class PostAuthorizationMiddleware
{
    /**
     * Process POST-like requests. Return sanitized data array on success,
     * or a string (JSON error) on failure. For non-POST methods, [].
     *
     * @return array|string
     */
    public static function getDataSet()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Never run on preflight
        if (strcasecmp($method, 'OPTIONS') === 0) {
            return [];
        }

        // Only gate POST (add PUT/PATCH if needed)
        if (!in_array(strtoupper($method), ['POST'], true)) {
            return [];
        }

        // 1) Authorization check first
        if (!PostAuthorizationService::checkAuthorizationHeader()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');

            // CORS headers already set earlier
            return json_encode([
                'error' =>
                    'not authorized based on authorization header',
            ]);
        }

        // 2) Read + sanitise body once
        $sanitizeInputService = new SanitizeInputService();
        $postInputService     = new PostInputService(
            $sanitizeInputService
        );

        $dataSet = $postInputService->getDataSet();

        writeLog('PostAuthorizationMiddleware', $dataSet);

        return $dataSet;
    }
}
