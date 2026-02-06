<?php
declare(strict_types=1);

namespace App\Services\BibleBrain;

use App\Configuration\Config;
use App\Http\HttpClientInterface;
use RuntimeException;

final class BibleBrainApiService
{
    public function __construct(
        private HttpClientInterface $http
    ) {}

    /**
     * Returns the API base URL, normalised.
     *
     * Acceptable config values:
     * - https://4.dbt.io
     * - https://4.dbt.io/api
     *
     * Result will always be:
     * - https://4.dbt.io/api
     */
    private static function apiBase(): string
    {
        $root = (string) Config::get(
            'endpoints.biblebrain',
            'https://4.dbt.io'
        );

        $root = rtrim($root, "/ \t\n\r\0\x0B");

        if (str_ends_with($root, '/api')) {
            return $root;
        }

        return $root . '/api';
    }

    /**
     * Performs a GET request to Bible Brain and decodes JSON.
     *
     * - Always includes: v=4 and key=...
     * - Throws if API key is missing
     * - Throws on non-JSON responses (so failures are loud, not silent)
     *
     * @param string               $endpoint Path under /api (no leading slash)
     * @param array<string,mixed>  $params   Query params
     *
     * @return array<string,mixed>
     */
    private function getJson(string $endpoint, array $params = []): array
    {
        $endpoint = ltrim($endpoint, "/ \t\n\r\0\x0B");

        $apiKey = (string) Config::get('api.bible_brain_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('Missing Bible Brain API key.');
        }

        $q = $params + [
            'v'   => '4',
            'key' => $apiKey,
        ];

        $url = self::apiBase() . '/' . $endpoint;
        $url .= '?' . http_build_query(
            $q,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $resp = $this->http->get($url);

        $body = (string) $resp->getBody();
        $json = json_decode($body, true);

        if (!is_array($json)) {
            throw new RuntimeException(
                'Bible Brain returned non-JSON response.'
            );
        }

        return $json;
    }

    /**
     * Fetch language by numeric language_id.
     *
     * Example: GET /api/languages/3969
     *
     * @return array<string,mixed>
     */
    public function fetchLanguageById(int $languageId): array
    {
        return $this->getJson("languages/{$languageId}");
    }

    /**
     * Fetch bibles by ISO 639-3 code (e.g., zlm).
     *
     * Note: parameter naming depends on Bible Brain;
     * keep this method but verify the correct query key in docs.
     *
     * @return array<string,mixed>
     */
    public function fetchBiblesByIso(string $iso): array
    {
        return $this->getJson('bibles', [
            'iso' => $iso,
        ]);
    }

    /**
     * Fetch filesets for a bible and return only text_* filesets.
     *
     * Bible Brain filesets use 'set_type_code' values like:
     * - text_json, text_usx, text_plain, text_format
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchTextFilesets(string $bibleId): array
    {
        $data = $this->getJson("bibles/{$bibleId}/filesets");

        if (!is_array($data)) {
            return [];
        }

        return array_values(array_filter(
            $data,
            static function ($row): bool {
                if (!is_array($row)) {
                    return false;
                }
                $type = (string) ($row['set_type_code'] ?? '');
                return str_starts_with($type, 'text_');
            }
        ));
    }
}
