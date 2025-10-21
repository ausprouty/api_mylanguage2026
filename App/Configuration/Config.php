<?php

namespace App\Configuration;

use Exception;

class Config
{
    private static $config = null;

    /**
     * Initialize the configuration by detecting the environment
     * and loading the appropriate configuration file.
     */
   public static function initialize(): void
    {
        if (self::$config !== null) {
            return; // already initialized
        }

        // 1) Let an env var win everywhere (cron-friendly)
        $environment = getenv('APP_ENV');

        // 2) If not set, decide by context
        if (!$environment) {
            $isCli = (PHP_SAPI === 'cli') || defined('STDIN');

            if ($isCli || empty($_SERVER['SERVER_NAME'])) {
                // CLI typically on server
                $environment = 'remote';
            } else {
                $host = $_SERVER['SERVER_NAME'] ?? '';
                $environment = in_array($host, ['localhost', '127.0.0.1'], true)
                    ? 'local'
                    : 'remote';
            }
        }

        $configFile = __DIR__
            . ($environment === 'local' ? 'private/.env.local.php' : 'private/.env.remote.php');

        if (!file_exists($configFile)) {
            throw new \Exception("Configuration file '{$configFile}' not found.");
        }

        self::$config = require $configFile;

        if (!is_array(self::$config)) {
            throw new \Exception(
                "Configuration file '{$configFile}' must return an array."
            );
        }

        // ---- Logging merge (config + env) ----
        $logging  = self::$config['logging'] ?? [];
        $cfgLevel = isset($logging['level'])
            ? strtolower((string)$logging['level'])
            : null;
        $envLevel = getenv('LOG_LEVEL')
            ? strtolower(getenv('LOG_LEVEL'))
            : null;

        // Validate level
        $valid = ['debug', 'info', 'warning', 'error', 'critical'];
        $level = $envLevel ?: ($cfgLevel ?: 'info');
        if (!in_array($level, $valid, true)) {
            $level = 'info';
        }
        self::$config['log_level'] = $level;

        // Choose default log file per SAPI; allow overrides in config
        $defaultFile = (PHP_SAPI === 'cli')
            ? ($logging['cli_file'] ?? 'translation-a.log')
            : ($logging['file'] ?? 'application.log');
        self::$config['log_file'] = $defaultFile;

        // (optional) expose env for convenience
        self::$config['env'] = $environment;
    }


    /**
     * Get a configuration value.
     *
     * @param string $key The configuration key. Supports dot notation for nested arrays.
     * @param mixed $default Optional default value if the key is not found.
     * @return mixed The configuration value or the provided default if the key is missing.
     * @throws Exception if a required configuration key is missing and no default is provided.
     */
    public static function get(string $key, $default = null)
    {
        if (self::$config === null) {
            self::initialize(); // Automatically initialize if not already done
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $keyPart) {
            if (!isset($value[$keyPart])) {
                if ($default !== null) {
                    error_log("Warning: Configuration key '{$key}' is not defined. Using default value.");
                    return $default;
                }

                throw new Exception("Configuration key '{$key}' is required but not defined.");
            }
            $value = $value[$keyPart];
        }

        return $value;
    }
    public static function getDir($key, $default = null)
    {
        if (!is_string($key) || trim($key) === '') {
            throw new \InvalidArgumentException("Invalid key provided for Config::getDir()");
        }

        $path = self::get("paths.$key", $default);
        if (!$path) {
            if ($default !== null) return self::get('base_dir') . $default;
            throw new \InvalidArgumentException("Directory path for '$key' not found.");
        }

        $base = rtrim(self::get('base_dir'), "/\\");
        // If $path is absolute, return it as-is (normalised)
        if (preg_match('#^([A-Za-z]:[\\\\/]|/|\\\\\\\\)#', $path)) {
            return rtrim(str_replace('\\', '/', $path), "/");
        }

        $joined = $base . '/' . ltrim($path, "/\\");
        return rtrim(str_replace('\\', '/', $joined), "/");
    }

    public static function getUrl($key)
    {
        $path = self::get("paths.$key");
        if (!$path) {
            throw new \InvalidArgumentException("URL for '$key' not found.");
        }

        return self::get('base_url') . $path;
    }
    
    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::get($key);
        if ($val === null) {
            // Don’t raise PHP warnings; just log once if you want
            error_log("Config: missing '{$key}', using default=" . ($default ? 'true' : 'false'));
            return $default;
        }
        if (is_bool($val)) return $val;
        $s = strtolower((string)$val);
        return in_array($s, ['1','true','yes','on'], true);
    }

     

    
}
