<?php

require __DIR__ . '/../../vendor/autoload.php';

use App\Cron\BibleBrainBibleCleanupService;
use App\Configuration\Config;

// Load your app config
Config::initialize(); //

// Load the DI container
$container = require __DIR__ . '/../Configuration/container.php';

/** @var BibleBrainBibleCleanupService $syncService */
$syncService = $container->get(BibleBrainBibleCleanupService::class);
$syncService->run();
