<?php

namespace App\Middleware;

use App\Services\Security\SanitizeInputService;
use App\Controllers\Data\PostInputController;
use App\Services\Security\PostAuthorizationService;

class PostAuthorizationMiddleware
{
    /**
     * Process POST-like requests. Return sanitized data array on success,
     * or a string on failure. For non-POST methods, returns [].
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

        // 1) Authorization check first (optional but efficient)
        if (!PostAuthorizationService::checkAuthorizationHeader()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            // CORS headers are already set by CORSMiddleware earlier
            return json_encode(['error' => 'not authorized based on authorization header']);
        }

        // 2) Sanitize once, log once
        $sanitizeInputService = new SanitizeInputService();
        $postInputController  = new PostInputController($sanitizeInputService);

        $dataSet = $postInputController->getDataSet(); // single read/parse
        writeLog('PostAuthorizationMiddleware', $dataSet);

        return $dataSet;
    }
}
