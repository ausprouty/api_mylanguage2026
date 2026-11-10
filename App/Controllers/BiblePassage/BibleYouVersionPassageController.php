<?php

namespace App\Controllers\BiblePassage;

use App\Services\Bible\YouVersionPassageService;
use App\Models\Bible\PassageModel;

class BibleYouVersionPassageController
{
    private YouVersionPassageService $biblePassageService;

    public function __construct(
        YouVersionPassageService $biblePassageService,
    ) {
        $this->biblePassageService = $biblePassageService;
    }
   public function fetchPassage(){
    return;
   }

    public function getPassageText()
    {
        return $this->biblePassageService->getPassageText();
    }

    public function getPassageUrl()
    {

        return $this->biblePassageService->getPassageUrl();
    }

    public function getReferenceLocalLanguage()
    {
        return $this->biblePassageService->getReferenceLocalLanguage();
    }
}
