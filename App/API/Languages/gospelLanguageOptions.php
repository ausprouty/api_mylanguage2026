<?php

use App\Controllers\Language\GospelLanguageController as GospelLanguageController;
use App\Controllers\ReturnDataController as ReturnDataController;

$languages = new GospelLanguageController();
$options = $languages->getBilingualOptions();
ReturnDataController::returnData($options);