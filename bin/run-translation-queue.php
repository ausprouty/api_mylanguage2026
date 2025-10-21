<?php
declare(strict_types=1);

/**
 * Local runner for the i18n translation queue.
 *
 * Usage:
 *   php bin/run-translation-queue.php --seconds=20 --batch=50 \
 *     --lang=fr --client=wsu --type=interface --subject=app --variant=wsu
 *   php bin/run-translation-queue.php --seconds=30 --fake
 */

use App\Configuration\Config;
use App\Services\LoggerService;
use App\Cron\TranslationQueueProcessor;
use App\Services\Language\NullTranslationBatchService;

// ---- Bootstrap -------------------------------------------------------------

$ROOT = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
require $ROOT . '/vendor/autoload.php';
putenv('APP_ENV=local');
Config::initialize();

// absolute project root (required in your env file)
$baseDir = rtrim(str_replace('\\','/', \App\Configuration\Config::get('base_dir')), '/');

// absolute logs directory (from paths.logs; falls back to /logs under baseDir)
$logsDir = rtrim(str_replace('\\','/', \App\Configuration\Config::getDir('logs', '/logs')), '/');

// use it
$cliLog = $logsDir . '/queue-kick.log';
if (!is_dir($logsDir)) { @mkdir($logsDir, 0777, true); }


/**
 * IMPORTANT: use the same container the web app uses.
 * Your project exposes it at Configuration/container.php.
 * That file returns a built PHP-DI container.
 *
 * If you’ve moved it, update the path below.
 */
/** @var \Psr\Container\ContainerInterface $container */
$container = require $ROOT . '/App/Configuration/container.php';

// ---- CLI args --------------------------------------------------------------

$seconds = 20;
$batch   = 50;
$fake    = false;

$scope = [
    'lang'    => null,
    'client'  => null,
    'type'    => null,
    'subject' => null,
    'variant' => null,
];

foreach ($argv as $arg) {
    if (preg_match('/^--seconds=(\d+)$/', $arg, $m)) {
        $seconds = (int) $m[1]; continue;
    }
    if (preg_match('/^--batch=(\d+)$/', $arg, $m)) {
        $batch = (int) $m[1]; continue;
    }
    if ($arg === '--fake') { $fake = true; continue; }
    if (preg_match('/^--lang=(.+)$/', $arg, $m)) {
        $scope['lang'] = $m[1]; continue;
    }
    if (preg_match('/^--client=(.+)$/', $arg, $m)) {
        $scope['client'] = $m[1]; continue;
    }
    if (preg_match('/^--type=(.+)$/', $arg, $m)) {
        $scope['type'] = $m[1]; continue;
    }
    if (preg_match('/^--subject=(.+)$/', $arg, $m)) {
        $scope['subject'] = $m[1]; continue;
    }
    if (preg_match('/^--variant=(.+)$/', $arg, $m)) {
        $scope['variant'] = $m[1]; continue;
    }
}

// ---- Minimal breadcrumb (proves this ran even if logger is CLI-silent) -----


$stamp = static function (string $msg, array $ctx = []) use ($cliLog): void {
    @file_put_contents(
        $cliLog,
        date('c') . ' ' . $msg .
        ($ctx ? ' ' . json_encode($ctx, JSON_UNESCAPED_SLASHES) : '') .
        PHP_EOL,
        FILE_APPEND
    );
};
$stamp('START', ['pid' => getmypid(), 'argv' => $argv]);

// ---- Resolve services & run ------------------------------------------------

/** @var LoggerService $logger */
$logger = $container->get(LoggerService::class);

/** @var TranslationQueueProcessor $proc */
$proc = $container->get(TranslationQueueProcessor::class);

// Optional: swap in a fake translator for smoke tests (no API calls).
if ($fake && method_exists($proc, 'setTranslator')) {
    $proc->setTranslator(new NullTranslationBatchService(prefixMode: true));
    $stamp('NullTranslator');
} else {
    $stamp('RealTranslator');
}

// Normalize CLI scope -> DB columns for processor consumption.
$effectiveScope = array_filter([
    'targetLanguageCodeGoogle' => $scope['lang'],
    'clientCode'               => $scope['client'],
    'resourceType'             => $scope['type'],
    'subject'                  => $scope['subject'],
    'variant'                  => $scope['variant'],
], fn($v) => $v !== null && $v !== '');

// Optional: pass scope filters if your processor supports them.
if (method_exists($proc, 'setScopeFilters')) {
    $proc->setScopeFilters($effectiveScope);
    $stamp('ScopeEffective', $effectiveScope);
} elseif (method_exists($proc, 'setFilters')) {
    $proc->setFilters($effectiveScope);
    $stamp('ScopeEffective', $effectiveScope);
} else {
    // Fall back to the original for visibility.
    $stamp('Scope', $scope);
}

// Optional dry-run flag if the processor supports it.
if ($fake && method_exists($proc, 'setDryRun')) {
    $proc->setDryRun(true);
}

// Honor --batch if the setter exists
if (method_exists($proc, 'setBatchSize')) {
    $proc->setBatchSize((int)$batch);
}

try {
    $logger::logInfo('run-translation-queue.start', [
        'seconds' => $seconds, 'batch' => $batch, 'scope' => $scope,
        'env' => Config::get('environment'),
    ]);
    $stamp('RUN', ['seconds' => $seconds, 'batch' => $batch]);

    $deadline   = microtime(true) + max(1, (int)$seconds);
    $iterations = 0;

    do {
        $proc->runOnce();      // processes up to the class’s internal $batchSize (defaults to 25)
        $iterations++;
        usleep(200_000);       // tiny backoff (200ms) so we don’t spin at 100% CPU
    } while (microtime(true) < $deadline);

    $logger::logInfo('run-translation-queue.done', [
        'seconds' => $seconds, 'batch' => $batch,
    ]);
    $stamp('END');
    echo "Done.\n";
    exit(0);

} catch (\Throwable $e) {
    $stamp('ERROR', ['type' => get_class($e), 'msg' => $e->getMessage()]);
    $logger::logInfo('run-translation-queue.error', [
        'type' => get_class($e), 'msg' => $e->getMessage(),
    ]);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
