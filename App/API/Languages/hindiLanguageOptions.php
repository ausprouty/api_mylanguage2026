<?php

use App\Controllers\ReturnDataController as ReturnDataController;
use App\Controllers\Language\HindiLanguageController as HindiLanguageController;

$languages = new HindiLanguageController();
$options = $languages->getLanguageOptions();
ReturnDataController::returnData($options);