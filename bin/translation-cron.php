<?php
declare(strict_types=1);



// ===== Project-scoped logging defaults (before anything else) =====
// All logs live under the working dir: /home/mylanguagenet/api2.mylanguage.net.au/logs
$__projectRoot = dirname(__DIR__);                 // …/api2.mylanguage.net.au
$__logsDir     = $__projectRoot . '/logs';
$__defaultLog  = $__logsDir . '/translation-a.log';
$__log         = getenv('LOG_FILE') ?: $__defaultLog;   // ENV wins; otherwise project logs
 $__lock        = $__log . '.lock';
$__lock        = $__log . '.lock';
$__heartbeat   = $__logsDir . '/translation-cron.last';
@mkdir($__logsDir, 0775, true);
if (!file_exists($__log)) { @touch($__log); @chmod($__log, 0664); }
@mkdir(dirname($__lock), 0775, true);
// Define canonical paths ONCE (used everywhere below)
define('LOG_PATH', $__log);
define('LOCK_PATH', $__lock);
define('HEARTBEAT_PATH', $__heartbeat);
// Point PHP internal error_log to the same project log
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH);
// Set a sane memory limit for cron jobs. Adjust if needed.
if (!ini_get('memory_limit') || ini_get('memory_limit') === '-1') {
    ini_set('memory_limit', '512M');
}
// Ultra-early heartbeat
@touch(HEARTBEAT_PATH);
 

// Ultra-early raw logger
function __raw_log(string $line): void {
    global $__log;
    $ts=(new DateTimeImmutable('now',new DateTimeZone('Australia/Sydney')))->format('Y-m-d H:i:sP');
    @file_put_contents($__log,"[$ts] $line\n",FILE_APPEND); echo "[$ts] $line\n";
}
__raw_log("BOOT launcher=".__FILE__." php=".PHP_BINARY." sapi=".PHP_SAPI);

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
 *    php translation-cron.php --max-secs=55 --batch-size=120 [--dry-run]
 */

use App\Cron\TranslationQueueProcessor;
use App\Configuration\Config;
use DI\ContainerBuilder;

/**
 * Robust STDERR write for non-CLI contexts.
 */

// --- CLI safeguard (safe even if STDERR isn't defined) ---
function write_stderr(string $msg): void {
    if (defined('STDERR')) {
        @fwrite(STDERR, $msg);
        return;
    }
    $fh = @fopen('php://stderr', 'wb');
    if ($fh) {
        @fwrite($fh, $msg);
        @fclose($fh);
        return;
    }
    echo $msg;
}

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
$isTty = function_exists('posix_isatty') && defined('STDIN')
    ? @posix_isatty(STDIN)
    : false;

if (!$isCli && !$isTty && getenv('GATEWAY_INTERFACE')) {
    write_stderr("This script must be run from CLI.\n");
    exit(1);
}

 

// project root = /home/mylanguagenet/api2.mylanguage.net.au
// bin/ is directly under project root, so vendor is one level up
require __DIR__ . '/../vendor/autoload.php';





// Load environment & config once
Config::initialize();
// LOG_PATH/LOCK_PATH/HEARTBEAT_PATH are already defined above for project logs.
// (We keep Config::initialize() so the rest of your app can use Config.)

/** Ensure log (and lock) directories exist */
(function (): void {
    $dir = dirname(LOG_PATH);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!file_exists(LOG_PATH)) { @touch(LOG_PATH); @chmod(LOG_PATH, 0664); }
    $lockDir = dirname(LOCK_PATH);
    if (!is_dir($lockDir)) { @mkdir($lockDir, 0755, true); }
    @touch(HEARTBEAT_PATH);
})();

/** Small logger helpers */
function log_line(string $msg): void
{
    $ts = (new DateTimeImmutable('now', new DateTimeZone('Australia/Sydney')))->format('Y-m-d H:i:sP');
    $line = '[' . $ts . '] ' . $msg . PHP_EOL;
    // append to file; also echo so cron redirection captures it
    @file_put_contents(LOG_PATH, $line, FILE_APPEND);
    echo $line;

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

/**
 * Build the TranslationQueueProcessor via PHP-DI.
 * Throws RuntimeException if php-di is missing or container build fails.
 */
function makeTranslationQueueProcessor(): TranslationQueueProcessor
{
    if (!class_exists(ContainerBuilder::class)) {
        throw new \RuntimeException('php-di not installed');
    }

    $builder = new ContainerBuilder();
    $defs = [
        __DIR__ . '/../App/Configuration/di/20-contracts.php',
        __DIR__ . '/../App/Configuration/di/30-services.php',
        __DIR__ . '/../App/Configuration/di/32-translation.php',
    ];
    $loadedAny = false;
    foreach ($defs as $def) {
        if (is_file($def)) {
            $builder->addDefinitions($def);
             log_line('di add ' . $def);
            $loadedAny = true;
        } else {
            log_line('di skip ' . $def);
        }
    }
    if (!$loadedAny) {
        log_line('ERROR no DI definition files found near bin/. '
            . 'Expected di/*.php or App/Configuration/di/*.php');
        throw new \RuntimeException('No DI definitions loaded');
    }
    $container = $builder->build();
    /** @var TranslationQueueProcessor $proc */
    return $container->get(TranslationQueueProcessor::class);
}

/** Main */
$startedAt = microtime(true);
$appEnv = Config::get('environment') ?: 'none';
$maxSecs = get_int_opt('max-secs', 1, 55);
$batchSize = get_int_opt('batch-size', 2, 120);
$dryRun = get_bool_opt('dry-run', 'DRY_RUN', false);

// Prevent overlap
$lockHandle = @fopen(LOCK_PATH, 'c');
if (!$lockHandle) {
    log_line('ERROR could not open lock file: ' . LOCK_PATH);
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
// Show launcher + PHP binary + args up front
global $argv;
log_line('launcher=' . __FILE__ . ' sapi=' . PHP_SAPI . ' php=' . PHP_BINARY . ' args=' . (isset($argv) ? implode(' ', $argv) : ''));
try {
    // Show which class file is being used to rule out stale code
    $rc = new \ReflectionClass(TranslationQueueProcessor::class);
    log_line('using class file=' . $rc->getFileName());

    // Set an execution time limit slightly above the loop time
    @set_time_limit($maxSecs + 10);

    $proc = makeTranslationQueueProcessor(); 

    if ($dryRun && method_exists($proc, 'setDryRun')) {
        $proc->setDryRun(true);
    }
    if (method_exists($proc, 'setBatchSize')) {
        $proc->setBatchSize((int)$batchSize);
    }

    // Run until deadline, calling runOnce() each cycle
    $deadline = time() + (int)$maxSecs;
    do {
        $proc->runOnce();
        usleep(200000);
    } while (time() < $deadline);

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
