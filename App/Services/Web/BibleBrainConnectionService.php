<?php

declare(strict_types=1);

namespace App\Services\Web;

use App\Configuration\Config;
use App\Services\LoggerService;

final class BibleBrainConnectionService extends WebsiteConnectionService
{

    /**
     * Constructor matches BibleGateway pattern:
     * build URL, log it, then hand off to WebsiteConnectionService.
     *
     * @param string               $endpoint
     * @param bool                 $autoFetch
     * @param bool                 $salvageJson
     * @param array<string,string> $query
     */
    public function __construct(
        string $endpoint,
        array $query = [],
        bool $autoFetch = true,
        bool $salvageJson = false
        
    ) {
        $url = self::buildUrl($endpoint, $query);
        LoggerService::logInfo('BibleBrainConnectionService-url', $url);
        parent::__construct($url, $autoFetch, $salvageJson);
    }

    /** Resolve the BibleBrain root from config (fallback to public site). */
    private static function baseUrl(): string
    {
        $root = (string) Config::get('endpoints.biblebrain', 'https://4.dbt.io');
        $baseUrl =  rtrim($root, "/ \t\n\r\0\x0B");
        LoggerService::logDebug('BibleBrainConnectionService-baseUrl', $baseUrl);
        return $baseUrl;
    }

    /** API key for BibleBrain/DBT (empty means "do not attach"). */
   private static function apiKey(): string
    {
        $key = (string) Config::get('api.bible_brain_key', '');
        if ($key === '') {
            throw new \RuntimeException(
                'BibleBrain API key missing (config: api.bible_brain_key)'
            );
        }
        return $key;
    }


    /**
     * Build a full URL and (optionally) attach query parameters + API key.
     *
     * @param string               $endpoint e.g. "/api/languages" or "api/languages"
     * @param array<string,string> $query
     */
    private static function buildUrl(string $endpoint, array $query = []): string
    {
        $endpoint = '/' . ltrim($endpoint, "/ \t\n\r\0\x0B");

        // Default DBP version, if your endpoints actually expect it.
        $query += ['v' => '4'];
        // Require key
        $query['key'] = self::apiKey();

        $url = self::baseUrl() . $endpoint;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }



    public function fetchLanguagesForIsoOrHl(?string $iso, ?string $hl): array
    {
 // Replace endpoint and parameter names to match the DBT API you use.
        $query = [];
        if ($iso) {
            $query['iso'] = $iso;
        }
        if ($hl) {
            $query['hl'] = $hl;
        }

        $conn = new self('/api/languages', $query,  true, true);
        return $conn->getJson() ?? [];
    }

    public function fetchTextFilesets(string $bibleId): array
    {
        // Replace endpoint and parameter names to match the DBT API you use.
        new self('/api/bibles/filesets', ['bible_id' => $bibleId], true, true);
        return $conn->getJson() ?? [];
    }
}
