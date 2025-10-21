<?php

namespace App\Controllers\BiblePassage\BibleBrain;

use App\Services\BiblePassage\BibleBrainPassageService;


class BibleBrainPassageController
{
    private $passageService;

    public function __construct(BibleBrainPassageService $passageService)
    {
        $this->passageService = $passageService;
    }

    public function getBiblePassage($languageCodeIso, $bibleReference)
    {
        return $this->passageService->fetchAndFormatPassage($languageCodeIso, $bibleReference);
    }
}
