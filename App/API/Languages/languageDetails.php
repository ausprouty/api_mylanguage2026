<?php

use App\Controllers\ReturnDataController as ReturnDataController;
use App\Models\Language\LanguageModel as LanguageModel;
use App\Services\Database\DatabaseService;
use App\Repositories\LanguageRepository;
use App\Factories\LanguageFactory;

$databaseService = new DatabaseService();
$languageFactory = new LanguageFactory($databaseService);
$languageRepository = new LanguageRepository($databaseService, $languageFactory);

$language = new LanguageModel($languageRepository);

$languageCodeHL = strip_tags($languageCodeHL);

$data = $language->findOneLanguageByLanguageCodeHL($languageCodeHL);
ReturnDataController::returnData($data);
