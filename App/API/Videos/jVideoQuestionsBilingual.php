<?php

use App\Controllers\BibleStudy\Bilingual\BilingualTemplateTranslationController as BilingualTemplateTranslationController;
use App\Controllers\ReturnDataController as ReturnDataController;


$questions = new BilingualTemplateTranslationController(
    $templateName = 'bilingualJesusVideoQuestions', 
    $translationFile ='video' , 
    $languageCodeHL1, 
    $languageCodeHL2);
ReturnDataController::returnData($questions->getTemplate());