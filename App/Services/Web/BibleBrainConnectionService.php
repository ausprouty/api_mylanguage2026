<?php
declare(strict_types=1);

namespace App\Services\Web;

use App\Configuration\Config;
use App\Http\HttpClientInterface;
use App\Services\LoggerService;

final class BibleBrainConnectionService extends WebsiteConnectionService
{
    public function __construct(
        private HttpClientInterface $http,
        private LoggerService $log
    ) {}

    /** Resolve the BibleBrain root from config (fallback to public site). */
    private static function baseUrl(): string
    {
        // Use endpoints.biblebrain if present; default to the public site.
        $root = (string) Config::get('endpoints.biblebrain', 'https://4.dbt.io');
        return rtrim($root, "/ \t\n\r\0\x0B");
    }
    /**
     * @param string $endpoint   e.g. "en/42/7.htm" (no leading slash)
     * @param bool   $salvageJson allow trimming pre-JSON preamble (usually false; pages are HTML)
     */

    public function fetchLanguagesForIsoOrHl(?string $iso, ?string $hl): array
    {
        // TODO: implement real call to BibleBrain API
        return [];
    }

    public function fetchTextFilesets(string $bibleId): array
    {
        // TODO: implement real call
        return [];
    }
}
