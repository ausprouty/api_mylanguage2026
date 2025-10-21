<?php

use App\Controllers\ReturnDataController as ReturnDataController;

$gospel= new GospelPageController();
$text = $gospel->getBilingualPage($page);
ReturnDataController::returnData($text);