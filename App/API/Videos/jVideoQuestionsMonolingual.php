<?php

use App\Controllers\ReturnDataController as ReturnDataController;
use App\Controllers\BibleStudy\Monolingual\MonolingualTemplateTranslationController as MonolingualTemplateTranslationController;

$questions = new MonolingualTemplateTranslationController(
    $templateName = 'monolingualJesusVideoQuestions', 
    $translationFile ='video' , 
    $languageCodeHL, 
);
ReturnDataController::returnData($questions->getTemplate());