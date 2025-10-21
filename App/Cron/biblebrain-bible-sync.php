<?php

require __DIR__ . '/../../vendor/autoload.php';

use App\Cron\BibleBrainBibleSyncService;
use App\Configuration\Config;

// Load your app config
Config::initialize(); //

// Load the DI container
$container = require __DIR__ . '/../Configuration/container.php';

/** @var BibleBrainBibleSyncService $syncService */
$syncService = $container->get(BibleBrainBibleSyncService::class);
$syncService->syncOncePerMonth();
