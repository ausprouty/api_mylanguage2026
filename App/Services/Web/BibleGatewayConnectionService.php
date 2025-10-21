<?php
declare(strict_types=1);

namespace App\Services\Web;

use App\Configuration\Config;
use App\Services\LoggerService;

class BibleGatewayConnectionService extends WebsiteConnectionService
{
    /** Resolve the BibleGateway root from config (fallback to public site). */
    private static function baseUrl(): string
    {
        // Use endpoints.biblegateway if present; default to the public site.
        $root = (string) Config::get('endpoints.biblegateway', 'https://www.biblegateway.com');
        return rtrim($root, "/ \t\n\r\0\x0B");
    }

    /**
     * @param string $endpoint e.g. "/passage/?search=John+3%3A16" (leading slash optional)
     * @param bool   $autoFetch   perform request immediately (default true)
     * @param bool   $salvageJson keep false; BibleGateway returns HTML
     */
    public function __construct(
        string $endpoint,
        bool $autoFetch = true,
        bool $salvageJson = false
    ) {
        $endpoint = '/' . ltrim($endpoint, "/ \t\n\r\0\x0B");

        $url = self::baseUrl() . $endpoint;

        LoggerService::logInfo('BibleGatewayConnectionService-url', $url);

        // BibleGateway returns HTML; keep salvageJson=false by default.
        parent::__construct($url, $autoFetch, $salvageJson);
    }

    public static function getBaseUrl(): string
    {
        return self::baseUrl();
    }
}
