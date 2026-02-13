<?php
/*
To run:

cd api2.mylanguage.net.au
php bin/best-bible-selection.php



SELECT COUNT(*) AS languages_all_missing_weight
FROM (
  SELECT languageCodeHL
  FROM bibles
  GROUP BY languageCodeHL
  HAVING SUM(weight IS NOT NULL) = 0
) x;


SELECT COUNT(*) AS languages_with_any_weight
FROM (
  SELECT languageCodeHL
  FROM bibles
  GROUP BY languageCodeHL
  HAVING SUM(weight IS NOT NULL) > 0
) x;



SELECT
  max_weight AS weight,
  COUNT(*)   AS language_count
FROM (
  SELECT languageCodeHL, MAX(weight) AS max_weight
  FROM bibles
  WHERE weight IS NOT NULL
  GROUP BY languageCodeHL
) t
WHERE max_weight IN (7, 9)
GROUP BY max_weight
ORDER BY max_weight DESC;

*/

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Configuration\Config;

Config::initialize();
$container = require __DIR__ . '/../App/Configuration/container.php';

$svc = $container->get(
    \App\Services\Bible\BestBibleSelectionService::class
);

$svc->run();

echo "OK\n";
exit(0);
