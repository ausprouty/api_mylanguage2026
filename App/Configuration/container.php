<?php

use DI\ContainerBuilder;

$builder = new ContainerBuilder();

// (Optional clarity; default is already true)
$builder->useAutowiring(true);

// Load our split DI definitions (ordered aggregator)
$builder->addDefinitions(__DIR__ . '/di/di-all.php');

// Simple env switch
$env = getenv('APP_ENV') ?: 'dev';

if ($env === 'prod') {
    // Compile the container for faster prod boot
    $cacheDir = __DIR__ . '/../var/cache/di';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $builder->enableCompilation($cacheDir);

    // Write proxies to disk (helps opcache & avoids temp files)
    $proxyDir = __DIR__ . '/../var/cache/proxy';
    if (!is_dir($proxyDir)) {
        @mkdir($proxyDir, 0775, true);
    }
    $builder->writeProxiesToFile(true, $proxyDir);
}

return $builder->build();
