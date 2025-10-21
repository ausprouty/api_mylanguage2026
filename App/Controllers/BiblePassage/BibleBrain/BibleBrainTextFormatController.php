<?php

namespace App\Controllers\BiblePassage\BibleBrain;

use App\Services\BiblePassage\BibleBrainPassageService;
use App\Models\Bible\PassageReferenceModel;
use App\Models\Bible\BibleModel;

class BibleBrainTextFormatController
{
    private $passageService;


    public function __construct(BibleBrainPassageService $passageService)
    {
        $this->passageService = $passageService;
    }

    public function getPassageText(BibleModel $bible, PassageReferenceModel $bibleReference)
    {
        // Fetch and format passage text using the service
        $formattedPassageText = $this->passageService->fetchAndFormatPassage(
            $bible,
            $bibleReference
        );

        return $formattedPassageText;
    }
}
