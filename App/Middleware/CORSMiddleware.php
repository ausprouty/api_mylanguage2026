<?php

namespace App\Middleware;

use App\Configuration\Config;
use App\Services\LoggerService;


/**
 * IMPORTANT – READ BEFORE DEBUGGING CORS
 *
 * This project uses Apache (.htaccess) to handle CORS preflight (OPTIONS)
 * and to emit Access-Control-* headers before requests reach PHP.
 *
 * That means:
 *   - OPTIONS requests may NEVER reach this middleware.
 *   - Access-Control-Allow-Headers seen in curl/browser may come from Apache,
 *     not from this class.
 *
 * If CORS behaviour does not match what you see here:
 *   1. Check the .htaccess file in the API root.
 *   2. Look for "Header always set Access-Control-*" directives.
 *   3. Confirm Apache is not short-circuiting OPTIONS before PHP.
 *
 * Do not assume this middleware controls preflight unless .htaccess
 * has been updated accordingly.
 */

class CORSMiddleware
{
    /**
     * Config keys used:
     * - cors.allowed_origins: string[]  (e.g. ["https://app.example.com",
     *                                      "http://localhost:*",
     *                                      "https://*.example.org"])
     * - cors.allowed_methods: string[]  (defaults below)
     * - cors.allowed_headers: string[]  (defaults below)
     * - cors.exposed_headers: string[]  (optional)
     * - cors.require_origin_paths: string[] patterns, e.g. ["/api/*"]
     * - environment: "prod"|"staging"|"local" (affects permissive wildcard)
     */
    public function handle($request, $next)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path   = $this->reqPath($_SERVER['REQUEST_URI'] ?? '/');
        $origin = trim($_SERVER['HTTP_ORIGIN'] ?? '');
        $env    = (string) (Config::get('environment') ?? 'prod');

        LoggerService::logWarning('cors.debug', 'cors headers', [
        'origin'  => $_SERVER['HTTP_ORIGIN'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'host'    => $_SERVER['HTTP_HOST'] ?? '',
        'method'  => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri'     => $_SERVER['REQUEST_URI'] ?? '',
        ]);


        // Always advertise Vary so caches/CDNs key properly
        header(
            'Vary: Origin, Access-Control-Request-Method, ' .
            'Access-Control-Request-Headers',
            true
        );

        // Fast exit for plain files we never want behind CORS logic
        if ($this->isRobotOrPublicPath($method, $path)) {
            // You may also add: header('X-Robots-Tag: noindex, nofollow');
            return $next($request);
        }

        $needsCors = $this->needsCorsCheck($method, $path);

        if ($origin === '') {
            if ($needsCors) {
                LoggerService::logWarning(
                    'CORSMiddleware-missing-origin',
                    ['method' => $method, 'path' => $path]
                );
                if ($method === 'OPTIONS') {
                    // Preflight with no Origin is malformed
                    http_response_code(400);
                    return $this->end();
                }
            } else {
                // Normal crawler/anonymous GET with no Origin — skip noise
                return $next($request);
            }
        }

        $allowed = $this->isOriginAllowed(
            (string) $origin,
            $env,
            (array) (Config::get('cors.allowed_origins') ?? [])
        );

        if (!$allowed) {
            // For preflight from disallowed origin, fail fast
            if ($method === 'OPTIONS') {
                LoggerService::logInfo(
                    'CORSMiddleware-preflight-block',
                    ['origin' => $origin, 'path' => $path]
                );
                http_response_code(403);
                return $this->end();
            }
            // For non-preflight, do not set ACAO → browser blocks it
            return $next($request);
        }

        // Build header sets
        $allowMethods = $this->headerList(
            Config::get('cors.allowed_methods') ??
            ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
        );

        // IMPORTANT:
        // Browsers (and axios/fetch) commonly include cache-control/pragma/if-modified-since
        // and custom request ids. If config provides cors.allowed_headers, we MERGE rather
        // than replace, otherwise preflight will succeed but the browser will block.
        $baseAllowedHeaders = [
            'Content-Type',
            'Authorization',
            'X-Requested-With',
            'X-Request-Id',
            'Cache-Control',
            'Pragma',
           'If-Modified-Since'
        ];

        $cfg = Config::get('cors.allowed_headers');
        $cfgArr = is_array($cfg) ? $cfg : ($cfg ? [$cfg] : []);

        // Merge config headers with the base set so we never accidentally
        // drop common browser/axios headers (cache-control, pragma, etc.).
        $merged = array_merge($baseAllowedHeaders, $cfgArr);
        $merged = array_values(array_unique(array_map('trim', $merged)));
        // de-dupe, keep order
        $uniq = [];
        foreach ($merged as $h) {
            $h = trim((string) $h);   
            if ($h === '') {
                continue;
            }
            $k = strtolower($h);
            if (!isset($uniq[$k])) {
                $uniq[$k] = $h;
            }
        }
        $allowHeaders = $this->headerList(array_values($uniq));
        LoggerService::logWarning('cors.debug', 'cors headers', [
            'allowHeaders'  => $allowHeaders,
        ]);
  
   
        $exposeHeaders = $this->headerList(
            Config::get('cors.exposed_headers') ?? []
        );

        // Set CORS headers for allowed origin
        header('Access-Control-Allow-Origin: ' . $origin, true);
        $allowCreds = (bool) (Config::get('cors.allow_credentials') ?? false);
        if ($allowCreds) {
            header('Access-Control-Allow-Credentials: true', true);
        }

        if ($exposeHeaders !== '') {
            header('Access-Control-Expose-Headers: ' . $exposeHeaders, true);
        }

        if ($method === 'OPTIONS') {
            $reqHdrs   =
                $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';

            header('Access-Control-Allow-Methods: ' . $allowMethods, true);
            // IMPORTANT:
            // Do not blindly echo the browser's requested headers back.
            // Instead, intersect with our allow-list so we never "allow" more
            // than intended, but we still satisfy legitimate requests like
            // "cache-control" used by some clients.
            $requested = $this->splitHeaderNames($reqHdrs);
            $allowed   = $this->splitHeaderNames($allowHeaders);
            $final     = $requested ? $this->intersectHeaderNames($requested, $allowed)
                                    : $allowed;
            LoggerService::logWarning('cors.debug', 'cors headers', [
                'requestedHeaders' => $requested,
                'allowedHeaders'   => $allowed,
                'finalHeaders'     => $final,
            ]);
            header('Access-Control-Allow-Headers: ' . implode(', ', $final), true);
   
            header('Access-Control-Max-Age: 86400', true);

            http_response_code(204);
            return $this->end();
        }

        return $next($request);
    }

    private function end(): string
    {
        // Return an empty body for middleware short-circuit
        return '';
    }

    private function needsCorsCheck(string $method, string $path): bool
    {
        if ($method === 'OPTIONS') {
            return true;
        }
        $patterns =
            (array) (Config::get('cors.require_origin_paths') ?? ['/api/*']);
        foreach ($patterns as $p) {
            if ($this->pathMatches($path, (string) $p)) {
                return true;
            }
        }
        return false;
    }

    private function isRobotOrPublicPath(string $method, string $path): bool
    {
        // Skip CORS for typical crawler hits and static roots on this host
        if ($method === 'GET') {
            if ($path === '/' || $path === '/robots.txt' || $path === '/favicon.ico') {
                return true;
            }
        }
        return false;
    }

    private function pathMatches(string $path, string $pattern): bool
    {
        // Simple glob: "/api/*" or "/public/*.json"
        $pattern = str_replace(
            ['*', '?'],
            ['.*', '.'],
            preg_quote($pattern, '#')
        );
        return (bool) preg_match('#^' . $pattern . '$#', $path);
    }

    private function reqPath(string $uri): string
    {
        $p = parse_url($uri);
        $path = $p['path'] ?? '/';
        return $path === '' ? '/' : $path;
    }

    private function isOriginAllowed(
        string $origin,
        string $env,
        array $rules
    ): bool {
        if ($origin === '') {
            return false;
        }
        $norm = $this->normalizeOrigin($origin);

        // Allow exact matches
        foreach ($rules as $rule) {
            $rule = (string) $rule;
            if ($rule === '') {
                continue;
            }
            if ($this->originRuleMatch($norm, $rule, $env)) {
                return true;
            }
        }

        // Dev convenience: allow localhost if env=local and not set
        if ($env !== 'prod' && $this->isLoopback($norm)) {
            return true;
        }

        return false;
    }

    private function originRuleMatch(
        string $origin,
        string $rule,
        string $env
    ): bool {
        $ruleNorm = $this->normalizeOrigin($rule);

        // Support wildcard subdomains and wildcard port
        // Examples:
        //   https://*.example.com
        //   http://localhost:*
        //   https://api.example.com
        $rx = preg_quote($ruleNorm, '#');
        $rx = str_replace('\*', '[^:/]+', $rx);
        $rx = preg_replace('#:(\d+|\*)$#', ':(\d+)', $rx);

        return (bool) preg_match('#^' . $rx . '$#i', $origin);
    }

    private function normalizeOrigin(string $o): string
    {
        // Keep scheme + host + optional port; drop path/query/hash
        $p = parse_url($o);
        $scheme = strtolower($p['scheme'] ?? '');
        $host   = strtolower($p['host'] ?? '');
        if ($scheme === '' || $host === '') {
            return '';
        }
        $port = isset($p['port']) ? ':' . (int) $p['port'] : '';
        return $scheme . '://' . $host . $port;
    }

    private function isLoopback(string $normOrigin): bool
    {
        return (bool) preg_match(
            '#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i',
            $normOrigin
        );
    }

    private function headerList($val): string
    {
        $arr = is_array($val) ? $val : [$val];
        $arr = array_map(fn($s) => trim((string) $s), $arr);
        $arr = array_filter($arr, fn($s) => $s !== '');
        return implode(', ', $arr);
    }
    
     /**
     * Parse a comma-separated header list into normalized header names.
     * Returns e.g. ["content-type","authorization"].
     */
    private function splitHeaderNames(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $csv));
        $parts = array_filter($parts, fn($s) => $s !== '');
        $parts = array_map(fn($s) => strtolower($s), $parts);
        // de-dupe, keep order
        $out = [];
        foreach ($parts as $p) {
            if (!in_array($p, $out, true)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /**
     * Intersect requested headers with allowed headers (case-insensitive).
     * Returns an array of header names in canonical "Title-Case" form.
     */
    private function intersectHeaderNames(array $requested, array $allowed): array
    {
        $allowedSet = array_flip($allowed);
        $out = [];
        foreach ($requested as $r) {
            if (isset($allowedSet[$r])) {
                // Canonical-ish casing for readability in responses
                $out[] = implode('-', array_map('ucfirst', explode('-', $r)));
            }
        }
        // If nothing matched, fall back to full allowed list (canonicalized)
        if (!$out) {
            foreach ($allowed as $a) {
                $out[] = implode('-', array_map('ucfirst', explode('-', $a)));
            }
        }
        return $out;
    }
}
