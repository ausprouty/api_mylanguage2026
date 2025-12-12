<?php

namespace App\Services\Language;

use App\Services\Bible\BibleLanguageAvailabilityService;
use App\Services\JesusVideo\JesusVideoLanguageAvailabilityService;
// etc...

/*
App/
  Services/
    Languages/
      LanguagesAvailableService.php  <-- orchestrator
    Bible/
      BibleLanguageAvailabilityService.php
    JesusVideo/
      JesusVideoLanguageAvailabilityService.php
    GospelTract/
      TractLanguageAvailabilityService.php
    HolySpirit/
      SpiritPresentationLanguageAvailabilityService.php
    Qna/
      QnaWebsiteLanguageAvailabilityService.php
    Translation/
      GoogleTranslatableLanguageService.php
*/

class LanguagesAvailableService
{
    // inject all the sub-services
    public function __construct(
        BibleLanguageAvailabilityService $bibleService,
        JesusVideoLanguageAvailabilityService $jesusVideoService,
        // ...
    ) {
        // store them
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function getLanguagesWithProducts(array $filters): array
    {
        // 1. Work out which products to include
        // 2. Get master list of languages (e.g. from hl_languages)
        // 3. Ask each sub-service: "What do you know about this language?"
        // 4. Build the response shape from section 2.
    }
}
