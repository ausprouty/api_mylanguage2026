<?php
declare(strict_types=1);

namespace App\Services;

use Exception;
use App\Support\Trace;
use App\Configuration\Config;

/**
 * LoggerService
 *
 * Single-file text logger with:
 * - Configurable level threshold (debug|info|warning|error|critical)
 * - One-line entries with JSON context (traceId, method, path, ip)
 * - File target chosen from config; falls back to temp if unwritable
 * - Optional mirroring to PHP error_log() or a specific file
 *
 * Config (supports legacy root keys and nested `logging.*`):
 *
 * // Legacy (still supported)
 * 'log_level'            => 'info',
 * 'log_file'             => 'application.log',
 * 'log_cli_file'         => 'translation-a.log',
 * 'log_mirror_error_log' => true, // or "C:/path/to/php_errors.log"
 * 'logs'                 => 'C:/ampp82/logs', // via Config::getDir('logs')
 *
 * // Preferred nested
 * 'logging' => [
 *   'mode'                 => 'write_log',       // informational only
 *   'level'                => 'info',
 *   'file'                 => 'application.log',
 *   'cli_file'             => 'translation-a.log',
 *   'log_mirror_error_log' => true               // or absolute path string
 * ]
 */
class LoggerService
{
    /** @var string|null Absolute path of the active log file. */
    private static ?string $logFile = null;

    /** @var int|null Cached numeric threshold for this process. */
    private static ?int $minLevelNum = null;

    /**
     * Mirror settings (lazy-resolved):
     * - enabled?: true/false
     * - file?: null means use PHP error_log(); string means append to path
     */
    private static ?bool $mirrorEnabled = null;
    private static ?string $mirrorFile  = null;

    // setting for debugging translation files
    private static ?bool $i18nDebugEnabled = null;

    // setting for debugging cron token flow
    private static ?bool $cronTokenDebugEnabled = null;

    /** Level mapping: debug(10) < info(20) < warning(30) < error(40) < critical(50) */
    private static function levelNum(string $lvl): int
    {
        $map = [
            'debug'    => 10,
            'info'     => 20,
            'warning'  => 30,
            'error'    => 40,
            'critical' => 50,
        ];
        $k = strtolower($lvl);
        return isset($map[$k]) ? $map[$k] : 20;  // default INFO
    }

    /**
     * Resolve the minimum level once per request/process.
     * Prefers `logging.level`, falls back to legacy `log_level`.
     */
    private static function minLevelNum(): int
    {
        if (self::$minLevelNum !== null) {
            return self::$minLevelNum;
        }

        $lvl = Config::get('logging.level', null);
        if ($lvl === null) {
            $lvl = Config::get('log_level', 'info');
        }

        self::$minLevelNum = self::levelNum((string) $lvl);
        return self::$minLevelNum;
    }
        /** Base flags for safe JSON logging. */
    private const JSON_FLAGS =
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PARTIAL_OUTPUT_ON_ERROR;


    /**
     * True if a candidate level should be written given the threshold.
     */
    private static function allowed(string $lvl): bool
    {
        return self::levelNum($lvl) >= self::minLevelNum();
    }

    /**
     * Public override for the current process (useful for ad-hoc web debug).
     */
    public static function overrideLevel(string $lvl): void
    {
        self::$minLevelNum = self::levelNum($lvl);
    }

    // ---------- Public convenience methods (structured logging) ----------

    /** @param array<string,mixed> $ctx */
    public static function logError(string $ctxName, mixed $msg, array $ctx = []): void
    {
        self::log('ERROR', $ctxName, $msg, $ctx);
    }

    /** @param array<string,mixed> $ctx */
    public static function logCritical(
        string $ctxName,
        mixed $msg,
        array $ctx = []
    ): void {
        self::log('CRITICAL', $ctxName, $msg, $ctx);
    }

    /** @param array<string,mixed> $ctx */
    public static function logWarning(
        string $ctxName,
        mixed $msg,
        array $ctx = []
    ): void {
        self::log('WARNING', $ctxName, $msg, $ctx);
    }

    /** @param array<string,mixed> $ctx */
    public static function logInfo(
        string $ctxName,
        mixed $msg,
        array $ctx = []
    ): void {
        self::log('INFO', $ctxName, $msg, $ctx);
    }

    /** @param array<string,mixed> $ctx */
    public static function logDebug(
        string $ctxName,
        mixed $msg,
        array $ctx = []
    ): void {
        self::log('DEBUG', $ctxName, $msg, $ctx);
    }
    /**
     * Shortcut for exception logging with consistent structure.
     *
     * @param array<string,mixed> $ctx
     */
       public static function logException(
        string $ctxName,
        mixed $e,
        array $ctx = []
    ): void {
        if ($e instanceof \Throwable) {
            $msg = get_class($e) . ': ' . $e->getMessage();
            $ctx = array_merge([
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ], $ctx);
            self::log('ERROR', $ctxName, $msg, $ctx);
            return;
        }
        // Back-compat: allow array or string payloads from legacy handlers.
        if (is_array($e)) {
            $msg = (string)($e['message'] ?? 'Exception (array payload)');
            // Don’t clobber caller-supplied keys if present.
            $ctx = array_merge($e, $ctx);
            self::log('ERROR', $ctxName, $msg, $ctx);
            return;
        }
        // Fallback: treat as string-like message.
        self::log('ERROR', $ctxName, (string) $e, $ctx);
    }

        /**
     * Encode a value to JSON for logs.
     */
    private static function toJson(mixed $value, bool $pretty = false): string
    {
        $flags = self::JSON_FLAGS | ($pretty ? JSON_PRETTY_PRINT : 0);
        $json  = json_encode($value, $flags);
        if ($json === false) {
            return '<<json_encode_error: ' . json_last_error_msg() . '>>';
        }
        return $json;
    }

    /**
     * Info-level JSON log helper.
     */
    public static function logInfoJson(
        string $tag,
        mixed $value,
        bool $pretty = false
    ): void {
        self::logInfo($tag, self::toJson($value, $pretty));
    }

    /**
     * Debug-level JSON log helper.
     */
    public static function logDebugJson(
        string $tag,
        mixed $value,
        bool $pretty = false
    ): void {
        self::logDebug($tag, self::toJson($value, $pretty));
    }

    
    /**
     * Debug logging for the i18n/translation pipeline.
     *
     * Controlled by Config key: logging.i18n_debug (bool).
     * If disabled, this is a no-op.
     *
     * $ctx may be an array OR a callable returning array so you can
     * defer building expensive context until logging is enabled.
     *
     * @param string                 $event
     * @param array|callable():array $ctx
     */
    public static function logDebugI18n(
       string $tag,
       string|int|float|bool|array|callable|null $ctx = null
    ) : void {
        if (!self::isI18nDebugEnabled()) {
            return;
        }
        $payload = [];
        if (is_array($ctx)) {
            $payload = $ctx;
        } elseif (is_callable($ctx)) {
            try {
                $v = $ctx();
                $payload = is_array($v) ? $v : ['msg' => (string) $v];
            } catch (\Throwable $e) {
                $payload = ['err' => $e->getMessage()];
            }
        }
        elseif ($ctx !== null) {
            // Accept scalars like int/float/bool/string
            // and anything stringable.
            $payload = ['msg' => (string) $ctx];
         }
        self::logDebug('i18n.' . $tag, $payload);
    }

    /**
    * Debug logging for cron token flow.
    *
    * Controlled by Config key: logging.cron_token_debug (bool).
    * If disabled, this is a no-op.
    *
    * $ctx may be an array OR a callable returning array so you can
    * defer building expensive context until logging is enabled.
    */
    public static function logDebugCronToken(
        string $tag,
        string|int|float|bool|array|callable|null $ctx = null
    ) : void {
        if (!self::isCronTokenDebugEnabled()) {
            return;
        }

        $payload = [];

        if (is_array($ctx)) {
            $payload = $ctx;
        } elseif (is_callable($ctx)) {
            try {
                $v = $ctx();
                $payload = is_array($v) ? $v : ['msg' => (string) $v];
            } catch (\Throwable $e) {
                $payload = ['err' => $e->getMessage()];
            }
        } elseif ($ctx !== null) {
            // Accept scalars like int/float/bool/string
            // and anything stringable.
            $payload = ['msg' => (string) $ctx];
        }

        self::log('DEBUG', 'cronToken.' . $tag, 'CronToken', $payload);
    }

    /**
     * True when logging.i18n_debug is on.
     * Value is cached after first read for performance.
     */
    public static function isI18nDebugEnabled() : bool
    {
        if (self::$i18nDebugEnabled === null) {
            self::$i18nDebugEnabled =
                \App\Configuration\Config::getBool('logging.i18n_debug', false);
        }
        return self::$i18nDebugEnabled;
    }

    /**
     * Allow tests/CLI to force or clear the flag cache at runtime.
     * Pass null to clear and re-read from Config on next use.
     */
    public static function setI18nDebugEnabled(?bool $state) : void
    {
        self::$i18nDebugEnabled = $state;
    }

    public static function isCronTokenDebugEnabled() : bool
    {
        if (self::$cronTokenDebugEnabled === null) {
            self::$cronTokenDebugEnabled =
                \App\Configuration\Config::getBool(
                    'logging.cron_token_debug',
                    false
                );
        }
        return self::$cronTokenDebugEnabled;
    }

    /**
     * Allow tests/CLI to force or clear the flag cache at runtime.
     */
    public static function setCronTokenDebugEnabled(?bool $state) : void
    {
        self::$cronTokenDebugEnabled = $state;
    }
 


    // ------------------------------- Core --------------------------------

    /**
     * Core writer: one line of text with JSON context.
     *
     * Format:
     * [YYYY-mm-dd HH:ii:ss] [LEVEL] [Context] Message {"traceId":"...","k":"v"}
     *
     * @param array<string,mixed> $ctx
     */
    private static function log(
        string $level,
        string $context,
        mixed $message,
        array $ctx = []
    ): void {
        if (!self::allowed($level)) {
            return;
        }

        if (!self::$logFile) {
            self::init();
        }

        $msg = is_string($message) ? $message : print_r($message, true);

        // Attach minimal request/trace context
        $ctx = array_merge([
            'traceId' => Trace::id(),
            'httpMethod' => $_SERVER['REQUEST_METHOD'] ?? null,
            'path'    => $_SERVER['REQUEST_URI'] ?? null,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        ], $ctx);
        // Add caller (class::method) if not provided explicitly by the caller
        // Use distinct keys to avoid clashing with HTTP 'method'
        if (!isset($ctx['caller']) || !isset($ctx['callerClass'])) {
            $ctx = self::enrichWithCaller($ctx);
        }
 
         // Let the destination (e.g., Apache/Nginx error_log) add its own timestamp.
        // Keep our line clean to avoid duplicate date prefixes.
        $line =  ' [' . strtoupper($level) . ']'
            . ' [' . $context . '] '
            . self::compactOneLine($msg)
            . ' ' . self::encodeJsonSafe($ctx);
        // Determine separator: newline + optional blank line
        $sep = PHP_EOL . (self::extraBlankLineEnabled() ? PHP_EOL : '');
        try {
            file_put_contents(self::$logFile, $line . $sep, FILE_APPEND | LOCK_EX);
            self::mirrorLine($line, $sep);
      
        } catch (Exception $e) {
            error_log('Logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Initialize file target from config; ensure directory exists/writable.
     * Prefers `logging.file`/`logging.cli_file`, falls back to legacy keys.
     */
    private static function init(): void
    {
        if (self::$logFile) {
            return;
        }

        // Directory selection: Config::getDir('logs') should point to a folder.
        $dir = rtrim((string) Config::getDir('logs'), '/\\');

        // File name selection: prefer nested logging.* keys
        $isCli = (php_sapi_name() === 'cli');
        $name  = null;

        if ($isCli) {
            $name = Config::get('logging.cli_file', null);
            if ($name === null) {
                $name = Config::get('log_cli_file', null);
            }
        }

        if ($name === null) {
            $name = Config::get('logging.file', null);
            if ($name === null) {
                $name = Config::get('log_file', 'application.log');
            }
        }

        $path = $dir . DIRECTORY_SEPARATOR . (string) $name;

        // Ensure directory exists and is writable; otherwise fallback to system temp.
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            error_log("LoggerService: '$dir' not writable; using temp dir");
            $tmp  = rtrim(sys_get_temp_dir(), '/\\');
            $path = $tmp . DIRECTORY_SEPARATOR . (string) $name;
        }

        self::$logFile = $path;
    }

    /**
     * Resolve "mirror to error log" behavior.
     * Supports:
     * - false/null : no mirroring
     * - true       : mirror via PHP error_log()
     * - string     : append to that file path
     *
     * Prefers `logging.log_mirror_error_log`, falls back to legacy root key.
     */
    private static function mirrorEnabled(): bool
    {
        if (self::$mirrorEnabled !== null) {
            return self::$mirrorEnabled;
        }

        $val = Config::get('logging.log_mirror_error_log', null);
        if ($val === null) {
            $val = Config::get('log_mirror_error_log', null);
        }

        if ($val === true) {
            self::$mirrorEnabled = true;
            self::$mirrorFile    = null;   // use PHP error_log()
        } elseif (is_string($val) && $val !== '') {
            self::$mirrorEnabled = true;
            self::$mirrorFile    = $val;   // target file
        } else {
            self::$mirrorEnabled = false;
            self::$mirrorFile    = null;
        }

        return self::$mirrorEnabled;
    }

    /**
     * If mirroring is enabled, send the already-formatted log line either
     * to PHP's error_log() or to the configured mirror file.
     */
     private static function mirrorLine(string $line, string $sep): void
    {
        if (!self::mirrorEnabled()) {
            return;
        }

        if (self::$mirrorFile) {
         // Ensure directory exists for mirror target
            $dir = dirname(self::$mirrorFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            // Best-effort (avoid breaking request flow on permission errors)
            @file_put_contents(self::$mirrorFile, $line . $sep, FILE_APPEND | LOCK_EX);
            return;
        }
         // error_log doesn't append a newline by itself
        error_log($line . $sep);
    }

        /**
     * Whether to add a blank line between entries (configurable).
     * Prefers `logging.blank_line_between`, falls back to true by default.
     */
    private static function extraBlankLineEnabled(): bool
    {
        $v = Config::get('logging.blank_line_between', null);
        if ($v === null) {
            // legacy/off-by-default switch could be added here if you like
            return true;
        }
        return (bool) $v;
    }

    // ------------------------------- Utils -------------------------------

    /** Compact multi-line strings into a single line for log entries. */
    private static function compactOneLine(string $s): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

        /**
     * Infer the immediate non-LoggerService caller (class & method) from the
     * stack. Best-effort, inexpensive trace (limit few frames).
     *
     * @param array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    private static function enrichWithCaller(array $ctx): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
        foreach ($trace as $frame) {
            $cls = $frame['class']  ?? null;
            $fun = $frame['function'] ?? null;
            if (!$cls || $cls === __CLASS__) {
                continue;
            }
            $ctx['callerClass'] = $ctx['callerClass'] ?? $cls;
            $ctx['caller']      = $ctx['caller']      ?? ($cls . '::' . (string) $fun);
            // Also expose method/function/line/file for convenience.
            $ctx['method']      = $ctx['method']      ?? ($cls . '::' . (string) $fun);
            $ctx['function']    = $ctx['function']    ?? (string) $fun;
            if (!isset($ctx['line']) && isset($frame['line'])) {
                $ctx['line'] = $frame['line'];
            }
            if (!isset($ctx['file']) && isset($frame['file'])) {
                $ctx['file'] = $frame['file'];
            }
            break;
        }
        return $ctx;
    }


    /**
     * Safe JSON encode for context; never throws.
     * @param array<string,mixed> $ctx
     */
    private static function encodeJsonSafe(array $ctx): string
    {
        $json = json_encode($ctx,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PARTIAL_OUTPUT_ON_ERROR
            | JSON_INVALID_UTF8_SUBSTITUTE
    );
  
        return ($json === false) ? '{}' : $json;
    }

    // ------------------------------ Overrides ----------------------------

    /**
     * Allow callers to override the log file at runtime (tests, one-off scripts).
     */
    public static function setLogFile(string $filePath): void
    {
        self::$logFile = $filePath;
    }
}
