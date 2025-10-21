<?php

namespace App\Services\BibleStudy;

use App\Services\Database\DatabaseService;

class TitleService{

    private $databaseService;

    public function __construct(DatabaseService $databaseService){
        $this->databaseService = $databaseService;
    }

    public function getTitleAndLessonNumber($study, $languageCodeHL){
         return ('finish this code');
    }
}
