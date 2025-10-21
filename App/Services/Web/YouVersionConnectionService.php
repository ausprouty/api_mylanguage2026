<?php
declare(strict_types=1);

namespace App\Services\Web;

use App\Configuration\Config;
use App\Services\LoggerService;

class YouVersionConnectionService extends WebsiteConnectionService
{
    /** Resolve YouVersion root from config (fallback to public reader). */
    private static function baseUrl(): string
    {
        // Use 'endpoints.youversion' if present; default to the public site.
        $root = (string) Config::get('endpoints.youversion', 'https://www.bible.com/bible');
        return rtrim($root, "/ \t\n\r\0\x0B") . '/';
    }

    /**
     * @param string $endpoint   e.g. "111/JHN.3.NIV" (no leading slash)
     * @param bool   $autoFetch  perform request immediately (default true)
     * @param bool   $salvageJson keep false; YouVersion returns HTML
     */
    public function __construct(
        string $endpoint,
        bool $autoFetch = true,
        bool $salvageJson = false
    ) {
        $endpoint = ltrim($endpoint, "/ \t\n\r\0\x0B");
        $url = self::baseUrl() . $endpoint;

        LoggerService::logInfo('YouVersionConnectionService-url', $url);

        // HTML expected; leave $salvageJson = false by default.
        parent::__construct($url, $autoFetch, $salvageJson);
    }

    public static function getBaseUrl(): string
    {
        return self::baseUrl();
    }
}
