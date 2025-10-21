// bin/biblebrain-cleanup-bibles.php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Configuration\Config;

Config::initialize();
$container = require __DIR__ . '/../App/Configuration/container.php';

$svc = $container->get(\App\Services\BibleBrain\BibleBrainBibleCleanupService::class);
$updated = $svc->run();

echo "Updated: {$updated}\n";
exit(0);
