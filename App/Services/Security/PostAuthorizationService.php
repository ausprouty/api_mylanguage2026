<?php

namespace App\Services\Security;

use App\Configuration\Config;

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
        // Read from config; adjust key as needed for your Config class
        $security = Config::get('security', []);
        $expected = $security['post_authorization_code'] ?? '';

        // If no code is configured, allow everything (dev mode).
        if ($expected === '') {
            return true;
        }

        // Grab the Authorization header (Apache/Nginx variations)
        $header =
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

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
            return false;
        }

        // Time-safe comparison
        return hash_equals($expected, $token);
    }
}
