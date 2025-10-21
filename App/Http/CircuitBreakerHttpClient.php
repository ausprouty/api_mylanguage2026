<?php
namespace App\Http;

use Exception;

class CircuitBreakerHttpClient implements HttpClientInterface
{
    private int $failures = 0;
    private int $openedAt = 0;

    public function __construct(
        private HttpClientInterface $inner,
        private int $threshold = 5,
        private int $cooldownSec = 30
    ) {}

    public function get(string $url, ?RequestOptions $opt = null): HttpResponse
    {
        if ($this->isOpen()) throw new Exception('Circuit open');
        try {
            $res = $this->inner->get($url, $opt);
            $this->failures = 0;
            return $res;
        } catch (\Throwable $e) {
            $this->failures++;
            if ($this->failures >= $this->threshold) {
                $this->openedAt = time();
            }
            throw $e;
        }
    }

    private function isOpen(): bool
    {
        if ($this->openedAt === 0) return false;
        if ((time() - $this->openedAt) > $this->cooldownSec) {
            $this->openedAt = 0; $this->failures = 0; return false;
        }
        return true;
    }
}
