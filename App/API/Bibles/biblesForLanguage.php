<?php
use App\Controller\ReturnDataController;
use App\Models\Bible\BibleModel;

$data = BibleModel::getAllBiblesByLanguageCodeHL($languageCodeHL);
ReturnDataController::returnData($data);
die;