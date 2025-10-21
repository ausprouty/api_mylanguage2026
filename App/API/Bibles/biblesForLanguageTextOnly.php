<?php

use App\Controllers\ReturnDataController;
use App\Models\Bible\BibleModel;

$data = BibleModel::getTextBiblesByLanguageCodeHL($languageCodeHL );
ReturnDataController::returnData($data);
die;