<?php
namespace App\Responses;

use App\Support\Trace;

/**
 * JsonResponse
 *
 * Minimal, header/exit-based JSON emitter with:
 * - success()/error() helpers (body + headers)
 * - notModified() for 304 (no body)
 * - maybeNotModified() preflight using ETag / Last-Modified
 *
 * NOTE: This class uses native header()/echo/exit semantics (no PSR-7).
 */
final class JsonResponse
{
    /**
     * Success response.
     *
     * Back-compat: if $headersOrStatus is an int and $status is null,
     * it's treated as the HTTP status.
     *
     * @param mixed                 $data
     * @param array<int|string,mixed>|int|null $headersOrStatus
     * @param int|null              $status
     */
    public static function success(
        $data = null,
        array|int|null $headersOrStatus = null,
        ?int $status = null
    ): void {
        $headers = [];
        if (is_int($headersOrStatus) && $status === null) {
            $status = $headersOrStatus;
        } elseif (is_array($headersOrStatus)) {
            $headers = $headersOrStatus;
        }
        $status = $status ?? 200;

        self::out(
            ['status' => 'ok', 'data' => $data],
            $status,
            $headers
        );
    }

    /**
     * Error response.
     *
     * @param string                $message
     * @param int                   $status
     * @param array<string,string>  $headers
     */
    public static function error(
        string $message,
        int $status = 500,
        array $headers = []
    ): void {
        self::out(
            ['status' => 'error', 'message' => $message],
            $status,
            $headers
        );
    }

    /**
     * 304 Not Modified with optional cache headers (e.g., ETag, Cache-Control).
     * Sends no body per RFC 7232/7234.
     *
     * @param array<string,string> $headers
     */
    public static function notModified(array $headers = []): void
    {
        http_response_code(304);

        // 304 should include caching headers but no Content-Type.
        foreach ($headers as $k => $v) {
            header($k . ': ' . $v);
        }

        // Explicitly avoid any body for 304.
        exit;
    }

    /**
     * Cache preflight helper.
     *
     * - If client validators match (ETag or Last-Modified), it emits 304 and exits.
     * - Otherwise, it *applies* the provided cache headers (ETag/Last-Modified/
     *   Cache-Control) to the response and returns false so caller can send body.
     *
     * Typical usage:
     *   if (JsonResponse::maybeNotModified($etag, $lastModTs, $cacheHeaders)) return;
     *   JsonResponse::success($payload, $cacheHeaders);
     *
     * @param string|null           $etag           Raw ETag; quotes will be added if missing.
     * @param int|null              $lastModifiedTs Unix timestamp for Last-Modified (UTC).
     * @param array<string,string>  $headers        Additional cache headers (e.g., Cache-Control).
     * @return bool  true = already handled (304 sent); false = proceed to write body.
     */
    public static function maybeNotModified(
        ?string $etag = null,
        ?int $lastModifiedTs = null,
        array $headers = []
    ): bool {
        $etagHdr = self::normalizeEtag($etag);
        $lmHdr   = self::formatHttpDate($lastModifiedTs);

        // Prepare the headers we'll either apply (200) or reuse (304).
        $cacheHeaders = [];
        if ($etagHdr !== null)        $cacheHeaders['ETag'] = $etagHdr;
        if ($lmHdr !== null)          $cacheHeaders['Last-Modified'] = $lmHdr;

        // Merge caller-provided headers (caller can set Cache-Control, etc.)
        $cacheHeaders = self::mergeHeaders($cacheHeaders, $headers);

        // Check validators from the request.
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH']    ?? null;
        $ifModSince  = $_SERVER['HTTP_IF_MODIFIED_SINCE']?? null;

        $etagMatches = ($etagHdr && $ifNoneMatch && trim($ifNoneMatch) === $etagHdr);
        $lmMatches   = ($lmHdr && $ifModSince && strtotime($ifModSince) >= ($lastModifiedTs ?? -1));

        // If the client's cache is fresh, return 304 immediately.
        if ($etagMatches || $lmMatches) {
            self::notModified($cacheHeaders);
            // exits
        }

        // Otherwise, apply cache headers now so the subsequent success()
        // response carries them too.
        foreach ($cacheHeaders as $k => $v) {
            header($k . ': ' . $v);
        }

        return false;
    }

    /**
     * Core emitter: sets status, merges default headers, adds traceId, prints JSON, exits.
     *
     * @param array<string,mixed>   $payload
     * @param int                   $status
     * @param array<string,string>  $headers
     */
    private static function out(
        array $payload,
        int $status,
        array $headers = []
    ): void {
        // Ensure meta + traceId present.
        if (!isset($payload['meta']) || !is_array($payload['meta'])) {
            $payload['meta'] = [];
        }
        $payload['meta']['traceId'] = $payload['meta']['traceId'] ?? Trace::id();

        http_response_code($status);

        // Sensible defaults (caller can override by providing same keys in $headers).
        $default = [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Trace-Id'   => $payload['meta']['traceId'],
        ];

        $headers = self::mergeHeaders($default, $headers);

        foreach ($headers as $k => $v) {
            header($k . ': ' . $v);
        }

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            // Last-resort fallback to avoid sending invalid JSON.
            $fallback = [
                'status'  => 'error',
                'message' => 'Encoding error',
                'meta'    => ['traceId' => $payload['meta']['traceId']],
            ];
            echo json_encode($fallback);
            exit;
        }

        echo $json;
        exit;
    }

    // -------------------------- Small utilities --------------------------

    /**
     * Normalize an ETag: if null, returns null; otherwise ensure it is quoted.
     */
    private static function normalizeEtag(?string $etag): ?string
    {
        if ($etag === null || $etag === '') {
            return null;
        }
        $t = trim($etag);
        // Accept already-quoted or weak ETags (W/"...")
        if ($t[0] === '"' || str_starts_with($t, 'W/"')) {
            return $t;
        }
        return '"' . $t . '"';
    }

    /**
     * RFC 7231 IMF-fixdate format (e.g., "Wed, 21 Oct 2015 07:28:00 GMT").
     */
    private static function formatHttpDate(?int $ts): ?string
    {
        return $ts ? gmdate('D, d M Y H:i:s', $ts) . ' GMT' : null;
    }

    /**
     * Merge headers with latter array taking precedence.
     *
     * @param array<string,string> $a
     * @param array<string,string> $b
     * @return array<string,string>
     */
    private static function mergeHeaders(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            $a[$k] = $v;
        }
        return $a;
    }
}
