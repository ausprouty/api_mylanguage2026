<?php
declare(strict_types=1);

/**
 * Translation queue cron runner.
 * /bin/translation-cron.php
 * 
 * Use in production and staging
 * Handles locking (no overlapping runs)
 *
 *  Sets up logging to your configured log file
 *  Reads CLI/env flags (--max-secs, --batch-size, --dry-run)
 *  Boots DI and constructs TranslationQueueProcessor with real deps
 *  Intended to be called by cron / scheduler, quietly, forever
 *
 * Usage:
 *   php translation-cron.php [maxSecs] [batchSize]
 *   php translation-cron.php --max-secs=55 --batch-size=120 [--dry-run]
 *
 * Env vars:
 *   APP_ENV=remote MAX_SECS=55 BATCH_SIZE=120 DRY_RUN=0
 */

use App\Cron\TranslationQueueProcessor;
use App\Configuration\Config;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

require __DIR__ . '/../../vendor/autoload.php';


// Load environment & config once
Config::initialize();
/**
 * Resolve log path:
 *  - LOG_DIR env var (if set)
 *  - project /logs (if writable)
 *  - system temp dir as fallback
 */



define('LOG_PATH', Config::get('logging.cli_file'));   // make log path available to functions
define('LOCK_PATH', $LOG_PATH . '.lock'); // optional, if you want the lock path too

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', $LOG);
// Set a sane memory limit for cron jobs. Adjust if needed.
if (!ini_get('memory_limit') || ini_get('memory_limit') === '-1') {
    ini_set('memory_limit', '512M');
}

/** Ensure log directory exists */
(function () use ($LOG): void {
    $dir = dirname($LOG);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
})();

/** Small logger helpers */
function log_line(string $msg): void
{
    file_put_contents(
        $LOG_PATH,
       '[' . date('c') . '] ' . $msg . PHP_EOL,
        FILE_APPEND
    );

}

function log_exception(\Throwable $e): void
{
    $extra = '';

    // If Twig is installed, include template context when possible
    if (class_exists('\Twig\Error\Error') && $e instanceof \Twig\Error\Error) {
        $src = method_exists($e, 'getSourceContext') &&
               $e->getSourceContext()
            ? $e->getSourceContext()->getName()
            : 'unknown';
        $line = method_exists($e, 'getTemplateLine')
            ? $e->getTemplateLine()
            : 0;
        $extra = " [twig template={$src} line={$line}]";
    }

    $parts = [];
    $cur = $e;
    $depth = 0;
    while ($cur && $depth < 5) {
        $parts[] = sprintf(
            "%s in %s:%d",
            $cur->getMessage(),
            $cur->getFile(),
            $cur->getLine()
        );
        $cur = $cur->getPrevious();
        $depth++;
    }

    log_line('ERROR ' . implode(' | caused by: ', $parts) . $extra);
    log_line('Trace:' . PHP_EOL . $e->getTraceAsString());
}

/** Parse CLI args and env with fallbacks */
function get_int_opt(string $long, int $pos, int $default): int
{
    $opt = null;

    // Long opts
    $opts = getopt('', [$long . '::']);
    if (isset($opts[$long]) && $opts[$long] !== false) {
        $opt = (string)$opts[$long];
    }

    // Env fallback
    if ($opt === null) {
        $envName = strtoupper(str_replace('-', '_', $long));
        $env = getenv($envName);
        if ($env !== false && $env !== '') {
            $opt = (string)$env;
        }
    }

    // Positional fallback
    global $argv;
    if ($opt === null && isset($argv[$pos])) {
        $opt = (string)$argv[$pos];
    }

    $val = (int)($opt ?? $default);
    return $val > 0 ? $val : $default;
}

function get_bool_opt(string $long, string $envName, bool $default): bool
{
    $opts = getopt('', [$long . '::']);
    if (array_key_exists($long, $opts)) {
        $raw = $opts[$long];
        if ($raw === false || $raw === null || $raw === '') {
            return true; // present without value
        }
        $raw = strtolower((string)$raw);
        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }
    $env = getenv($envName);
    if ($env !== false) {
        $env = strtolower((string)$env);
        return in_array($env, ['1', 'true', 'yes', 'on'], true);
    }
    return $default;
}

/** Main */
$startedAt = microtime(true);
$appEnv = Config::get('environment') ?: 'none';
$maxSecs = get_int_opt('max-secs', 1, 55);
$batchSize = get_int_opt('batch-size', 2, 120);
$dryRun = get_bool_opt('dry-run', 'DRY_RUN', false);

// Prevent overlap
$lockHandle = @fopen($LOCK, 'c');
if (!$lockHandle) {
    log_line('ERROR could not open lock file: ' . $LOCK);
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    log_line('skipped: lock held by another process');
    exit(0);
}

log_line('cron start APP_ENV=' . $appEnv .
    ' maxSecs=' . $maxSecs .
    ' batchSize=' . $batchSize .
    ' dryRun=' . ($dryRun ? '1' : '0') .
    ' cwd=' . getcwd());

try {
    // Show which class file is being used to rule out stale code
    $rc = new \ReflectionClass(TranslationQueueProcessor::class);
    log_line('using class file=' . $rc->getFileName());

    // Set an execution time limit slightly above the loop time
    @set_time_limit($maxSecs + 10);

    $proc = new TranslationQueueProcessor();

    if ($dryRun && method_exists($proc, 'setDryRun')) {
        $proc->setDryRun(true);
    }

    $proc->runCron($maxSecs, $batchSize);

    $elapsed = microtime(true) - $startedAt;
    $mem = memory_get_peak_usage(true);
    log_line(sprintf(
        'cron done in %.3fs peakMem=%.1fMB',
        $elapsed,
        $mem / (1024 * 1024)
    ));
    exit(0);

} catch (\Throwable $e) {
    log_exception($e);
    exit(1);

} finally {
    if (isset($lockHandle) && is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
