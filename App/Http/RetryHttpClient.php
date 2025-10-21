<?php
declare(strict_types=1);

namespace App\Http;

final class RetryHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $inner,
        private int $maxAttempts = 3,
        private int $baseDelayMs = 200,
        private array $retryStatuses = [429,500,502,503,504],
    ) {}

    public function get(string $url, ?RequestOptions $opt = null): HttpResponse
    {
        $attempt = 0;
        do {
            $attempt++;
            $resp = $this->inner->get($url, $opt);
            if (!in_array($resp->code, $this->retryStatuses, true)) {
                return $resp;
            }
            usleep($this->baseDelayMs * 1000 * $attempt);
        } while ($attempt < $this->maxAttempts);

        return $resp; // last attempt’s response
    }
}
