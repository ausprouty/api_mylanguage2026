<?php
declare(strict_types=1);

namespace App\Services\Web;

use App\Configuration\Config;
use App\Services\LoggerService;

class BibleWordConnectionService extends WebsiteConnectionService
{
    /** Resolve WordProject root from config, with a safe fallback. */
    private static function baseUrl(): string
    {
        // Your config key is endpoints.wordproject
        $root = (string) Config::get('endpoints.wordproject', 'https://wordproject.org/bibles');
        return rtrim($root, "/ \t\n\r\0\x0B") . '/';
    }

    /**
     * @param string $endpoint   e.g. "en/42/7.htm" (no leading slash)
     * @param bool   $salvageJson allow trimming pre-JSON preamble (usually false; pages are HTML)
     */
    public function __construct(string $endpoint, bool $salvageJson = false)
    {
        $endpoint = ltrim($endpoint, "/ \t\n\r\0\x0B");
        $url = self::baseUrl() . $endpoint;

        LoggerService::logInfo('BibleWordConnectionService-url', $url);

        // Auto-fetch with optional JSON salvage (typically HTML, so false is fine)
        parent::__construct($url, true, $salvageJson);
    }

    /** Back-compat: single array with code/body/headers/etc. */
    public function response(): array
    {
        return $this->asArray();
    }

    /** Convenience: HTML body */
    public function html(): string
    {
        return $this->getBody();
    }

    /** Optional helper if callers need it */
    public static function getBaseUrl(): string
    {
        return self::baseUrl();
    }
}
