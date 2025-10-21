<?php
use App\Controllers\ReturnDataController as ReturnDataController;
use App\Controllers\Video\JesusVideoSegmentController as JesusVideoSegmentController;


$segments = new JesusVideoSegmentController($languageCodeJF);
$segments->selectAllSegments();
if ($languageCodeHL =='eng00'){
    $data = $segments->formatWithEnglishTitle();
}
else{
    $data = $segments->formatWithEthnicTitle($languageCodeHL);
}
ReturnDataController::returnData($data);
