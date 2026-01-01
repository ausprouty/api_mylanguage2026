<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Responses\JsonResponse;
use App\Services\SeasonalService;
use App\Services\LoggerService;

final class SeasonalTextController
{
    private SeasonalService $seasonalService;

    public function __construct(SeasonalService $seasonalService)
    {
        $this->seasonalService = $seasonalService;
    }

    /**
     * Route:
     *   GET /api/v2/translate/seasonal/{site}/{languageCodeGoogle}
     *
     * Args keys:
     *   - site
     *   - languageCodeGoogle
     *
     * Returns:
     *   { "seasonal": <object|null> }
     */
    public function webFetch(array $args): JsonResponse
    {
        $site = isset($args['site']) ? (string) $args['site'] : '';
        $lang = isset($args['languageCodeGoogle'])
            ? (string) $args['languageCodeGoogle']
            : 'en';

        $site = $this->normalizeSite($site);
        if ($site === '') {
            return new JsonResponse(
                ['error' => 'Missing or invalid site'],
                400
            );
        }

        $lang = $this->normalizeGoogleLang($lang);

        // 30 days cache as agreed (frontend also checks date window).
        header('Cache-Control: public, max-age=2592000');

        $seasonal = $this->seasonalService->getActiveSeasonForSite(
            $site,
            $lang
        );
        LoggerService::logInfo('StudyTextController', ['seasonal'=> $seasonal]);

        return JsonResponse::success(['seasonal' => $seasonal]);
    }

    private function normalizeSite(string $site): string
    {
        $site = trim($site);
        if ($site === '') {
            return '';
        }

        // allow: letters, numbers, underscore, dash
        if (!preg_match('/^[a-z0-9_-]+$/i', $site)) {
            return '';
        }

        return strtolower($site);
    }

    private function normalizeGoogleLang(string $lang): string
    {
        $lang = trim($lang);
        if ($lang === '') {
            return 'en';
        }

        // Accept "en", "hi", "ta", "zh-CN", "pt-BR", etc.
        // Keep it permissive but safe.
        if (!preg_match('/^[a-z]{2,3}(-[A-Z]{2})?$/', $lang)) {
            return 'en';
        }

        // Normalise case: "zh-cn" -> "zh-CN"
        $parts = explode('-', $lang);
        if (count($parts) === 2) {
            return strtolower($parts[0]) . '-' . strtoupper($parts[1]);
        }

        return strtolower($lang);
    }
}
