<?php
declare(strict_types=1);

use App\Configuration\Config;
use App\Services\Database\DatabaseService;

$ROOT = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
require $ROOT . '/vendor/autoload.php';
putenv('APP_ENV=local');
Config::initialize();

/** @var \Psr\Container\ContainerInterface $container */
$container = require $ROOT . '/App/Configuration/container.php';

/** @var DatabaseService $db */
$db  = $container->get(DatabaseService::class);
$pdo = $db->pdo();
$pdo->exec("SET time_zone = '+00:00'");

$pdo->beginTransaction();

$stm = $pdo->prepare("
    UPDATE i18n_translation_queue
    SET status   = 'queued',
        lockedAt = NULL,
        lockedBy = NULL,
        attempts = 0,
        runAfter = UTC_TIMESTAMP()
    WHERE status IN ('processing','failed')
       OR runAfter > UTC_TIMESTAMP()
");
$stm->execute();
$affected = $stm->rowCount();

$pdo->commit();


