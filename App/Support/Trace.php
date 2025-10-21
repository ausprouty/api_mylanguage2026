<?php

namespace App\Support;

use App\Configuration\Config;

final class Trace
{
    /** @var string|null */
    private static $id = null;

    public static function init(?string $id = null): void
    {
        if ($id !== null && $id !== '') {
            self::$id = $id;
            return;
        }
        // Try to reuse incoming header (if you run behind a proxy)
        $incoming = $_SERVER['HTTP_X_TRACE_ID'] ?? null;
        if (is_string($incoming) && $incoming !== '') {
            self::$id = $incoming;
            return;
        }
        // Generate a new one
        self::$id = bin2hex(random_bytes(8)); // 16 hex chars
    }

    public static function id(): string
    {
        if (self::$id === null) {
            self::init();
        }
        return self::$id;
    }
    /** Separator: newline + optional blank line (mirrors LoggerService). */
    private static function sep(): string
    {
        $enabled = true;
        // Respect the same config key your LoggerService uses
        if (class_exists(Config::class)) {
            $v = Config::get('logging.blank_line_between', null);
            if ($v !== null) $enabled = (bool) $v;
        }
        return PHP_EOL . ($enabled ? PHP_EOL : '');
    }

    
     public static function info(string $message, array $context = []): void
     {
        error_log(
            '[INFO][Trace] ' . $message . ' ' .
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
            self::sep()
        );
     }

    public static function error(string $message, array $context = []): void
     {  
           error_log(
            '[ERROR][Trace] ' . $message . ' ' .
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
            self::sep()
        );
     }
}
