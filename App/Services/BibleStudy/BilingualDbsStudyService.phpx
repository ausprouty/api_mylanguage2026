<?php

namespace App\Services\BibleStudy;

use App\Services\BibleStudy\BilingualStudyService;


class BilingualDbsStudyService extends BilingualStudyService
{

    public function getTwigTranslationArray(): array
    {
        // Get the array from the parent class
        $parentTranslations = parent::getTwigTranslationArray();
        $data =  $this->translationService->loadTranslation($this->languageCodeHL1, $this->study);
        // Add additional translations specific to MonolingualLifeStudyService
        $questionTwigKey = $this->studyReferenceInfo->getQuestionTwigKey();


        $additionalTranslations = [
            'topic_sentence' =>  $data[$questionTwigKey],

        ];
        // Merge the parent and additional translations
        return array_merge($parentTranslations, $additionalTranslations);
    }
}
