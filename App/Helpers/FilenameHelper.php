<?php

namespace App\Helpers;

use App\Repositories\LanguageRepository;


class FilenameHelper {

    private $languageRepository;

    public function __construct(LanguageRepository $languageRepository)
    {
        $this->languageRepository = $languageRepository;
    }
    public function bilingualDbsPublicFilename($languageCodeHL1, $languageCodeHL2, $lesson, $type = 'DBS') {
        $lang1 = $this->languageRepository->getEnglishNameFromCodeHL($languageCodeHL1);
        $lang2 = $this->languageRepository->getEnglishNameFromCodeHL($languageCodeHL2);
        return trim($type . '#' . $lesson . '(' . $lang1 . '-' . $lang2 . ')');
    }

    public function monolingualDbsPublicFilename($lesson, $languageCodeHL1, $type = 'DBS') {
        $lang1 = $this->languageRepository->getEnglishNameFromCodeHL($languageCodeHL1);
        return trim($type . '#' . $lesson . '(' . $lang1 . ')');
    }
}
