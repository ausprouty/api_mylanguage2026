<?php

 use App\Controllers\BibleStudy\DbsStudyController as  DbsStudyController;
 use App\Controllers\ReturnDataController as ReturnDataController;

$lessons = new DbsStudyController();
if (!isset ($languageCodeHL1)){
    $data = $lessons->formatWithEnglishTitle();
}
else{
    $data = $lessons->formatWithEthnicTitle($languageCodeHL1);
}
ReturnDataController::returnData($data);
