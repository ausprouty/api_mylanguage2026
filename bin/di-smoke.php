#!/usr/bin/env php
<?php
declare(strict_types=1);

error_reporting(E_ALL);
putenv('APP_ENV=local');
$_ENV['APP_ENV'] = 'local';
$_SERVER['APP_ENV'] = 'local';

ini_set('display_errors', '1');

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;

$builder = new ContainerBuilder();

$defAll = __DIR__ . '/../App/Configuration/di/di-all.php';
$config  = require $defAll;            // di-all.php returns the merged array
if (!is_array($config)) {
    fwrite(STDERR, "di-all.php did not return an array\n");
    exit(2);
}

$builder->addDefinitions($config);     // pass the merged array directly
$container = $builder->build();

// For smoke: iterate keys from the merged array
$defs = $config;

$ok = 0; $fail = 0; $failures = [];
foreach (array_keys($defs) as $key) {
    try {
        $container->get($key);
        $ok++;
    } catch (\Throwable $e) {
        $fail++;
        $failures[] = [$key, $e->getMessage()];
    }
}

echo "DI smoke test: {$ok} ok, {$fail} failed\n";
if ($failures) {
    echo "---- Failures ----\n";
    foreach ($failures as [$k, $msg]) {
        echo "$k\n  → $msg\n";
    }
    exit(1);
}
