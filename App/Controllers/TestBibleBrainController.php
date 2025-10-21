<?php

namespace App\Controllers;

use App\Services\Bible\BibleBrainLanguageService;

class TestBibleBrainController
{
    private BibleBrainLanguageService $bibleBrainLanguageService;

    public function __construct(BibleBrainLanguageService $bibleBrainLanguageService)
    {
        $this->bibleBrainLanguageService = $bibleBrainLanguageService;
    }

    public function logFiveLanguages(): string
    {
        $this->bibleBrainLanguageService->testLogFiveBibleBrainLanguages();
        return 'Logged five BibleBrain languages to bibleBrain log';
    }
}
