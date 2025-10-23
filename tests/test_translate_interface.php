<?php

require_once __DIR__ . '/../bootstrap.php';      

use App\Services\Language\TranslationService;
use App\Repositories\LanguageRepository;
use App\Services\Database\DatabaseService;
use App\Factories\LanguageFactory;
use App\Services\LoggerService;


// Setup: create services manually or using your container
$databaseService = new DatabaseService(); // Adjust if you use dependency injection
$languageFactory = new LanguageFactory($databaseService);
$languageRepo    = new LanguageRepository($databaseService, $languageFactory);
$translationService = new TranslationService($databaseService, $languageRepo);

// Run the translation test
$app = 'dbs';
$languageCodeHL = 'frn00'; // French

$message = "Translating interface file for {$app} into {$languageCodeHL}...\n";
LoggerService::logInfo('TranslationTest', "$message");
 echo $message;
$result = $translationService->loadInterfaceTranslation($app, $languageCodeHL);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
