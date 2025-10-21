<?php
declare(strict_types=1);

namespace App\Services\BibleBrain;

use App\Configuration\Config;
use App\Services\Web\BibleBrainConnectionService as WebBibleBrain;
use App\Http\HttpClientInterface;

final class BibleBrainApiService
{
    public function __construct(
        private WebBibleBrain $web,          // your web-layer connection
        private HttpClientInterface $http     // already DI'd for web layer
    ) {}

    /** Base like https://4.dbt.io/api */
    private static function apiBase(): string
    {
        $root = (string) Config::get('endpoints.biblebrain', 'https://4.dbt.io');
        $root = rtrim($root, "/ \t\n\r\0\x0B");
        return str_ends_with($root, '/api') ? $root : ($root . '/api');
    }

    /** GET and decode JSON with BibleBrain defaults merged */
    private function getJson(string $endpoint, array $params = []): array
    {
        $endpoint = ltrim($endpoint, "/ \t\n\r\0\x0B");
        $q = $params + [
            'v'      => '4',
            'key'    => (string) Config::get('api.bible_brain_key', ''),
            'format' => 'json',
        ];
        $url = self::apiBase() . '/' . $endpoint . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
        $resp = $this->http->get($url);
        $json = json_decode($resp->getBody(), true);
        return is_array($json) ? $json : [];
    }

    /** Example high-level API calls */
    public function fetchBiblesByIso(string $iso): array
    {
        return $this->getJson('bibles', ['language_code' => $iso]);
    }

    public function fetchTextFilesets(string $bibleId): array
    {
        // Only text types; filter client-side for now
        $data = $this->getJson("bibles/{$bibleId}/filesets");
        return array_values(array_filter($data, static function ($row) {
            $type = $row['type'] ?? '';
            return str_starts_with($type, 'text_');
        }));
    }
}
