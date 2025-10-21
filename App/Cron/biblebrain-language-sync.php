<?php

require __DIR__ . '/../../vendor/autoload.php';

use App\Cron\BibleBrainLanguageSyncService;
use App\Configuration\Config;

// Load your app config
Config::initialize(); //

// Load the DI container
$container = require __DIR__ . '/../Configuration/container.php';

/** @var BibleBrainLanguageSyncService $syncService */
$syncService = $container->get(BibleBrainLanguageSyncService::class);
$syncService->syncOncePerMonth();
