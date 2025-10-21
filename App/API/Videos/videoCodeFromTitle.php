<?php

use App\Controllers\ReturnDataController as ReturnDataController;
use App\Controllers\Video\VideoController as VideoController;

$videoCode = VideoController::getVideoCodeFromTitle($title, $languageCodeHL);
ReturnDataController::returnData($videoCode);
