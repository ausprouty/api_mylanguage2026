<?php
/* To run:

cd /home/mylanguagenet/api2.mylanguage.net.au
php bin/biblebrain-bible-sync.php

*/


declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Configuration\Config;

Config::initialize();
$container = require __DIR__ . '/../App/Configuration/container.php';

$svc = $container->get(
    \App\Services\BibleBrain\BibleBrainBibleSyncService::class
);

$svc->syncOncePerMonth();

echo "OK\n";
exit(0);
