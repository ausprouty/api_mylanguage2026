<?php

use App\Controllers\ReturnDataController as ReturnDataController;
use App\Models\Video\VideoModel as VideoModel;

$result = VideoModel::getLanguageCodeJF($languageCodeHL);
ReturnDataController::returnData($result);