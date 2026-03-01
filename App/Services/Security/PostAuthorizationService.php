<?php

namespace App\Services\Security;

use App\Configuration\Config;
use App\Services\LoggerService;


class PostAuthorizationService
{

    /**
     * Check the Authorization header against the configured code.
     *
     * Returns true if:
     *  - no code is configured (dev/disabled), OR
     *  - the header contains the correct code.
     *
     * @return bool
     */
    public static function checkAuthorizationHeader(): bool
    {
        $security = Config::get('security', []);
        $resolved = self::resolveExpectedCode($security);
        $expected = $resolved['code'];
        $expectedKey = $resolved['key'];

        // If no code is configured, allow everything (dev mode).
        if ($expected === '') {
            return true;
        }

        // Grab the Authorization header (Apache/Nginx variations)
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        // Some Windows/Apache/PHP setups don't populate HTTP_AUTHORIZATION.
        // Fall back to getallheaders() if available.
        if ($header === '' && function_exists('getallheaders')) {
            $all = getallheaders();
            if (is_array($all)) {
                // Header keys can vary in case
                if (isset($all['Authorization'])) {
                    $header = (string) $all['Authorization'];
                } elseif (isset($all['authorization'])) {
                    $header = (string) $all['authorization'];
                }
            }
        }

        if ($header === '') {
            self::logAuthFailure('missing_header', [
                'expectedKey' => $expectedKey,
                'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
            ]);
             return false;
         }

        // Support either:
        //   Authorization: Bearer <code>
        // or
        //   Authorization: <code>
        if (stripos($header, 'Bearer ') === 0) {
            $token = trim(substr($header, 7));
        } else {
            $token = trim($header);
        }

        if ($token === '') {
            self::logAuthFailure('empty_token', [
                'expectedKey' => $expectedKey,
                'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
                'headerLen' => strlen($header),
                'bearer' => (stripos($header, 'Bearer ') === 0) ? 1 : 0,
            ]);
  
            return false;
        }

        // Time-safe comparison
        $ok = hash_equals($expected, $token);
        if (!$ok) {
            self::logAuthFailure('mismatch', [
                'expectedKey' => $expectedKey,
                'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
                'headerLen' => strlen($header),
                'tokenLen' => strlen($token),
                'bearer' => (stripos($header, 'Bearer ') === 0) ? 1 : 0,
            ]);
        }
         return $ok;
     }

    


    /**
     * Resolve expected code.
     * Supports:
     *  - security.post_authorization_code (single shared code), OR
     *  - security.post_authorization_codes (map keyed by Origin)
     *
     * @return array{code:string,key:string}
     */
    private static function resolveExpectedCode(array $security): array
    {
        $codes = $security['post_authorization_codes'] ?? null;
        if (is_array($codes)) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if ($origin !== '' && isset($codes[$origin])) {
                return [
                    'code' => (string) $codes[$origin],
                    'key'  => 'origin:' . $origin,
                ];
            }
        }

        $single = (string) ($security['post_authorization_code'] ?? '');
        return [
            'code' => $single,
            'key'  => 'single',
        ];
    }

    /**
     * Log auth failure without leaking secrets.
     *
     * @param array<string,mixed> $ctx
     */
    private static function logAuthFailure(string $reason, array $ctx = []): void
    {
        $ctx = array_merge([
            'reason' => $reason,
            'server_has_HTTP_AUTHORIZATION' =>
                isset($_SERVER['HTTP_AUTHORIZATION']) ? 1 : 0,
            'server_has_REDIRECT_HTTP_AUTHORIZATION' =>
                isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? 1 : 0,
        ], $ctx);

        LoggerService::logWarning('security.postAuth', 'POST auth failed', $ctx);
    }
}
