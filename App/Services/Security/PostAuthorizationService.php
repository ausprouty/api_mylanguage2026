<?php

namespace App\Services\Security;

use App\Configuration\Config;
use App\Services\LoggerService;


class PostAuthorizationService
{
    private const AUTH_LOG = 'C:\\ampp\\htdocs\\api_mylanguage2026\\logs\\post-auth.log';

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
        // Read from config; adjust key as needed for your Config class
        $security = Config::get('security', []);
        $expected = $security['post_authorization_code'] ?? '';

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
/* 
        LoggerService::LogInfo('PostAuthorizationService', [
            'has_HTTP_AUTHORIZATION' => isset($_SERVER['HTTP_AUTHORIZATION']) ? 1 : 0,
            'has_REDIRECT_HTTP_AUTHORIZATION' =>
                isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? 1 : 0,
            'header_len' => strlen($header),
        ]);
 
*/

        if ($header === '') {
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
            self::logAuthFailure('empty_token', $expected, $header);
            return false;
        }

        // Time-safe comparison
        $ok = hash_equals($expected, $token);
        if (!$ok) {
            self::logAuthFailure('mismatch', $expected, $header, $token);
        }
        return $ok;
    }
    
    private static function logAuthFailure(
        string $reason,
        string $expected,
        string $header,
        string $token = ''
    ): void {
        // Mask sensitive values but keep enough to debug whitespace/prefix issues
        $mask = function (string $s): string {
            $s = (string) $s;
            $len = strlen($s);
            if ($len <= 8) return str_repeat('*', $len);
            return substr($s, 0, 4) . str_repeat('*', $len - 8) . substr($s, -4);
        };

        $msg = [
            'ts' => date('c'),
            'reason' => $reason,
            'expected_len' => strlen($expected),
            'expected_mask' => $mask($expected),
            'header_len' => strlen($header),
            'header_raw' => $header,
            'token_len' => strlen($token),
            'token_mask' => $mask($token),
            'server_has_HTTP_AUTHORIZATION' => isset($_SERVER['HTTP_AUTHORIZATION']) ? 1 : 0,
            'server_has_REDIRECT_HTTP_AUTHORIZATION' => isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? 1 : 0,
        ];
        @file_put_contents(self::AUTH_LOG, json_encode($msg) . PHP_EOL, FILE_APPEND);
    }
 }
