<?php
namespace App\Http;

use Psr\SimpleCache\CacheInterface;

class CachedHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $inner,
        private CacheInterface $cache,
        private int $ttl = 300
    ) {}

    public function get(string $url, ?RequestOptions $opt = null): HttpResponse
    {
        $key = 'GET:' . sha1($url . '|' . serialize($opt));
        $hit = $this->cache->get($key);
        if ($hit instanceof HttpResponse) return $hit;

        $res = $this->inner->get($url, $opt);
        if ($res->code >= 200 && $res->code < 400) {
            $this->cache->set($key, $res, $this->ttl);
        }
        return $res;
    }
}
