<?php

namespace App\Configuration;

use Exception;
use App\Services\LoggerService;

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
        // Decide environment (CLI won't have $_SERVER vars)
        $envFromVar = getenv('APP_ENV') ?: getenv('APP_MODE');
        if ($envFromVar) {
            $environment = strtolower($envFromVar);
        } elseif (PHP_SAPI === 'cli') {
            $environment = 'remote';
        } else {
            $server = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
            $environment = (in_array($server, ['localhost','127.0.0.1'], true))
                ? 'local'
                : 'remote';
        }

        // Where is your /private folder?
        // A) If /private lives under this directory: App/Configuration/private/...
        $baseDir = __DIR__;
        // B) If /private is at project root (sibling of /App), use:
        // $baseDir = dirname(__DIR__, 2);

        $envFile = ($environment === 'local') ? '.env.local.php' : '.env.remote.php';
        $configFile = self::joinPath($baseDir, 'private', $envFile);

        if (!is_file($configFile) || !is_readable($configFile)) {
            throw new \Exception("Configuration file '{$configFile}' not found again.");
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

        if ($path === null || $path === '') {
            if ($default !== null && $default !== '') {
                $base = rtrim(self::get('base_dir'), "/\\");
                return $base . '/' . ltrim($default, "/\\");
            }

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
        /**
     * Fetch a boolean config value with robust coercion.
     * Accepts true/false, 1/0, "true/false", "on/off", "yes/no" (case-insensitive).
     * Unrecognised values fall back to $default.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $val = null;
        try {
            $val = self::get($key);
        } catch (\Throwable $e) {
            // Missing or loader error — use default (optionally log once)
            error_log(
                "Config: missing '{$key}', using default=" .
                ($default ? 'true' : 'false')
            );
            return $default;
        }

        if ($val === null) {
            return $default;
        }
        if (is_bool($val)) {
            return $val;
        }
        if (is_int($val)) {
            return $val !== 0;
        }
        if (!is_scalar($val)) {
            return $default; // arrays/objects: refuse and use default
        }

        $s = trim((string) $val);
        $parsed = filter_var($s, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }    
    public static function getInt(string $key, int $default = 0): int
    {
        try {
            $val = self::get($key, $default);
        } catch (\Throwable $e) {
            return $default;
        }

        if (is_int($val)) {
            return $val;
        }

        if (is_numeric($val)) {
            return (int) $val;
        }

        return $default;
    }


        /** Join path segments safely, cross-platform. */
    private static function joinPath(string ...$parts): string
    {
        $clean = [];
        foreach ($parts as $i => $p) {
            if ($p === '' || $p === null) continue;
            // trim trailing separators (except keep leading on first part like "C:\")
            $clean[] = $i === 0 ? rtrim($p, "/\\") : trim($p, "/\\");
        }
        return implode(DIRECTORY_SEPARATOR, $clean);
    }


     

    
}
