<?php
use App\Controllers\ReturnDataController as ReturnDataController;
use App\Controllers\Video\JesusVideoSegmentController as JesusVideoSegmentController;


$segments = new JesusVideoSegmentController($languageCodeJF);

ReturnDataController::returnData($data);
