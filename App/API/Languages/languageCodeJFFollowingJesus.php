<?php
use App\Controllers\ReturnDataController as ReturnDataController;
use App\Models\Video\VideoModel as VideoModel;

$result = VideoModel::getLanguageCodeJFFollowingJesus($languageCodeHL);
ReturnDataController::returnData($result);