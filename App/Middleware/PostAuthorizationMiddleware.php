<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Security\SanitizeInputService;
use App\Services\Data\PostInputService;
use App\Services\Security\PostAuthorizationService;
use App\Services\LoggerService;

class PostAuthorizationMiddleware
{
    public static function isPostLike(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strcasecmp($method, 'OPTIONS') === 0) return false;
        return in_array(strtoupper($method), ['POST'], true);
    }

    /**
     * @throws \RuntimeException when unauthorized
     */
    public static function authorizeOrThrow(): void
    {
        if (!PostAuthorizationService::checkAuthorizationHeader()) {
            throw new \RuntimeException(
                'not authorized based on authorization header'
            );
        }
    }

    /**
     * Always returns an array dataset for POST; otherwise [].
     *
     * @return array
     * @throws \RuntimeException when unauthorized
     */
    public static function getDataSet(): array
    {
        if (!self::isPostLike()) {
            return [];
        }

        self::authorizeOrThrow();

        $sanitizeInputService = new SanitizeInputService();
        $postInputService = new PostInputService($sanitizeInputService);
        $dataSet = $postInputService->getDataSet();

        LoggerService::logInfo(
            'PostAuthorizationMiddleware.dataset',
            ['dataset' => $dataSet]
        );

        return is_array($dataSet) ? $dataSet : [];
    }
}
