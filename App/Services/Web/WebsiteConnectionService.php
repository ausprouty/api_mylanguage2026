<?php

namespace App\Services\Web;

use Exception;
use JsonException;
use App\Services\LoggerService;

class WebsiteConnectionService
{
    /** Request config */
    private const TIMEOUT_SEC = 20;
    private const CONNECT_TIMEOUT_SEC = 10;
    private const MAX_REDIRECTS = 10;
    private const RETRY_MAX = 3;
    private const RETRY_BASE_MS = 250;   // 0.25s, then 0.5s, 1.0s...
    // Only throttle/retry on server-side failures (and rate limiting).
    // All other non-2xx responses should hard-stop the run.
    private const RETRY_HTTP = [429, 500, 502, 503, 504];
    /** Response state */
    protected string $url;
    protected string $body = '';
    protected ?array $json = null;
    protected int $httpCode = 0;
    protected ?string $contentType = null;
    protected array $headers = [];
    protected ?string $finalUrl = null;
    protected int $elapsedMs = 0;

    /** Error state */
    protected int $curlErrno = 0;
    protected ?string $curlError = null;

    /** Behavior flags */
    protected bool $salvageJson = false; // tolerate preamble before JSON

    /**
     * @param string $url
     * @param bool   $autoFetch       If true, do request in constructor.
     * @param bool   $salvageJson     If true, trim junk before first '{'/'['.
     */
    public function __construct(
        string $url,
        bool $autoFetch = true,
        bool $salvageJson = false
    ) {
        $this->url = $url;
        $this->salvageJson = $salvageJson;

        if ($autoFetch) {
            $this->fetch();
        }
    }

    /**
     * Execute the HTTP GET with retries and capture headers.
     */
    public function fetch(): void
    {
        $attempt = 0;
        $start = (int) (microtime(true) * 1000);

        while (true) {
            $attempt++;
            try {
                $this->doCurlRequest();
                $this->sanitizeBody();
                $this->decodeIfJson();
                break; // success
            } catch (Exception $e) {
                // If non-retriable or out of retries, rethrow
                if (!$this->shouldRetry($attempt)) {
                    throw $e;
                }
                $delay = $this->retryDelayMs($attempt);
                LoggerService::logWarning(
                    'WebsiteConnectionService-retry',
                    "attempt={$attempt} delayMs={$delay} url={$this->url}"
                );
                usleep($delay * 1000);
            }
        }

        $this->elapsedMs = (int) (microtime(true) * 1000) - $start;
        LoggerService::logInfo(
            'WebsiteConnectionService-done',
            json_encode([
                'code'   => $this->httpCode,
                'ctype'  => $this->contentType,
                'ms'     => $this->elapsedMs,
                'final'  => $this->finalUrl,
            ])
        );
    }

    /**
     *  Single attempt with cURL. Throws on fatal/network/non-2xx.
     */
    protected function doCurlRequest(): void
    {
        $this->resetState();

        LoggerService::logInfo(
            'WebsiteConnectionService-fetch',
            $this->url
        );

        $ch = curl_init();
        $hdrs = [];
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => self::MAX_REDIRECTS,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEC,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SEC,
            CURLOPT_ENCODING       => '', // allow all encodings
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HL-API/1.0)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_HEADER         => false,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$hdrs) {
                $len = strlen($line);
                $lineTrim = trim($line);
                if ($lineTrim === '' || strpos($line, 'HTTP/') === 0) {
                    return $len;
                }
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $key = strtolower(trim($parts[0]));
                    $val = trim($parts[1]);
                    // If repeated headers, keep last (simple approach)
                    $hdrs[$key] = $val;
                }
                return $len;
            },
        ]);

        $result = curl_exec($ch);

        $this->curlErrno = curl_errno($ch);
        $this->curlError = $this->curlErrno ? curl_error($ch) : null;

        if ($result === false) {
            $msg = 'cURL error ' . $this->curlErrno . ': ' . $this->curlError;
            LoggerService::logError('WebsiteConnectionService-curl', $msg);
            curl_close($ch);
            throw new Exception($msg);
        }
         
        $this->body = (string) $result;
        $len = strlen($this->body);
        $preview = substr($this->body, 0, 800);
        $hash = substr(hash('sha256', $this->body), 0, 16);

        LoggerService::logInfo(
            'WebsiteConnectionService-body',
            "len={$len} sha256_16={$hash} preview=" . $preview
        );


        $this->httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
        $this->finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: null;
        $this->headers = $hdrs;

        curl_close($ch);
        
        // Hard-stop on anything other than a 2xx response.
        // (Throttling/retry decisions are handled by shouldRetry().)
        if ($this->httpCode < 200 || $this->httpCode > 299) {
            $msg = 'HTTP ' . $this->httpCode . ' from ' . ($this->finalUrl
                ?: $this->url);

            // Log a small preview of the body to help diagnose WAF/HTML errors
            // without dumping huge responses into logs.
            $preview = trim(substr($this->body, 0, 300));
            LoggerService::logError(
                'WebsiteConnectionService-http',
                $msg . ($preview !== '' ? ' bodyPreview=' . $preview : '')
            );

            throw new Exception($msg);
        }
    }

    /**
     * Remove BOM and normalize line endings.
     */
    protected function sanitizeBody(): void
    {
        // Strip UTF-8 BOM if present
        if (strncmp($this->body, "\xEF\xBB\xBF", 3) === 0) {
            $this->body = substr($this->body, 3);
        }
        // Normalize CRLF -> LF
        $this->body = str_replace(["\r\n", "\r"], "\n", $this->body);

        // Optional salvage: trim any preamble before first JSON token.
        if ($this->salvageJson && $this->looksLikeJsonWithPreamble()) {
            $i = $this->firstJsonTokenPos($this->body);
            if ($i > 0) {
                $snippet = substr($this->body, 0, min(120, $i));
                LoggerService::logWarning(
                    'WebsiteConnectionService-salvage',
                    $snippet
                );
                $this->body = substr($this->body, $i);
            }
        }
    }

    protected function decodeIfJson(): void
    {
        if (!$this->isJsonResponse()) {
            $this->json = null;
            return;
        }

        try {
            $this->json = json_decode(
                $this->body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            $msg = 'JSON decode error: ' . $e->getMessage();
            LoggerService::logError('WebsiteConnectionService-json', $msg);
            $this->json = null;
        }
    }

    protected function isJsonResponse(): bool
    {
        if ($this->contentType &&
            stripos($this->contentType, 'json') !== false) {
            return true;
        }
        // Heuristic: trimmed starts with JSON token
        $t = ltrim($this->body);
        return $t !== '' && ($t[0] === '{' || $t[0] === '[');
    }

    protected function looksLikeJsonWithPreamble(): bool
    {
        $i = $this->firstJsonTokenPos($this->body);
        if ($i <= 0) return false;
        $prefix = substr($this->body, 0, $i);
        // If prefix contains HTML tags or PHP warnings, try salvage.
        return (strpos($prefix, '<') !== false) ||
               (stripos($prefix, 'warning') !== false) ||
               (stripos($prefix, 'deprecated') !== false) ||
               (stripos($prefix, 'notice') !== false);
    }

    protected function firstJsonTokenPos(string $s): int
    {
        $i1 = strpos($s, '{');
        $i2 = strpos($s, '[');
        if ($i1 === false && $i2 === false) return -1;
        if ($i1 === false) return $i2;
        if ($i2 === false) return $i1;
        return min($i1, $i2);
    }

    protected function shouldRetry(int $attempt): bool
    {
        if ($attempt >= self::RETRY_MAX) return false;

        // Retry on network errors
        if ($this->curlErrno !== 0) return true;

        // Retry (throttle) only on selected server-side codes.
        // Everything else should stop immediately to avoid hammering providers.
        if (in_array($this->httpCode, self::RETRY_HTTP, true)) {
            return true;
        }

        return false;
    }

    protected function retryDelayMs(int $attempt): int
    {
        // Exponential backoff: base * 2^(attempt-1)
        $delay = self::RETRY_BASE_MS * (1 << ($attempt - 1));

        // Honor Retry-After (seconds) if provided
        $ra = $this->headers['retry-after'] ?? null;
        if ($ra !== null && ctype_digit($ra)) {
            $delay = max($delay, ((int) $ra) * 1000);
        }
        return $delay;
    }

    protected function resetState(): void
    {
        $this->body = '';
        $this->json = null;
        $this->httpCode = 0;
        $this->contentType = null;
        $this->headers = [];
        $this->finalUrl = null;
        $this->curlErrno = 0;
        $this->curlError = null;
    }

    /** ------------ Public accessors ------------ */

    /** Raw response body (HTML, JSON text, etc.). */
    public function getBody(): string
    {
        return $this->body;
    }

    /** Decoded JSON as array, or null if not JSON / failed to decode. */
    public function getJson(): ?array
    {
        return $this->json;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    /** All response headers (lower-cased keys). */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** Final URL after redirects, if any. */
    public function getFinalUrl(): ?string
    {
        return $this->finalUrl;
    }

    /** Milliseconds elapsed for the last fetch. */
    public function getElapsedMs(): int
    {
        return $this->elapsedMs;
    }

    /** cURL errno (0 if none). */
    public function getCurlErrno(): int
    {
        return $this->curlErrno;
    }

    /** cURL error text, or null. */
    public function getCurlError(): ?string
    {
        return $this->curlError;
    }

    /** Convenience: expose the whole response as a flat array */
    public function asArray(): array
    {
        return [
            'code'    => $this->httpCode,
            'ctype'   => $this->contentType,
            'body'    => $this->body,
            'headers' => $this->headers,
            'final'   => $this->finalUrl,
            'ms'      => $this->elapsedMs,
            'curl'    => [
                'errno' => $this->curlErrno,
                'error' => $this->curlError,
            ],
            // When JSON was detected/decoded
            'json'    => $this->json,
        ];
    }
}
