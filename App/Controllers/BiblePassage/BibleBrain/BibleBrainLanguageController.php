<?php

namespace App\Controllers\BiblePassage\BibleBrain;

use App\Services\Bible\BibleBrainLanguageService;
use App\Repositories\LanguageRepository;

class BibleBrainLanguageController
{
    private $languageService;
    private $languageRepository;

    public function __construct(
        LanguageRepository $languageRepository, 
        BibleBrainLanguageService $languageService)
    {
        $this->languageRepository = $languageRepository;
        $this->languageService = $languageService;
    }

    public function getLanguagesFromCountryCode($countryCode)
    {
        return $this->languageService->fetchLanguagesByCountry($countryCode);
    }

    public function clearCheckedBBBibles()
    {
        $this->languageRepository->clearCheckedBBBibles();
    }

    public function getNextLanguageForLanguageDetails()
    {
        return $this->languageRepository->getNextLanguageforLanguageDetails();
    }

    public function setLanguageDetailsComplete($languageCodeIso)
    {
        $this->languageRepository->setLanguageDetailsComplete($languageCodeIso);
    }

    public function updateFromLanguageCodeIso($languageCodeIso, $name)
    {
        $this->languageService->processLanguageUpdate($languageCodeIso, $name);
    }
}
