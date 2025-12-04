<?php

declare(strict_types=1);

namespace App\Services\BiblePassage;

use App\Model\PassageModel;
use App\Repository\LanguageRepository;
use App\Service\Numerals\NumeralSystemConverter;

final class ReferenceLocaliserService
{
    public function __construct(
        private LanguageRepository $languageRepo,
        private NumeralSystemConverter $numeralConverter
    ) {
    }

    /**
     * Localises digits in referenceLocalLanguage based on HL code.
     */
    public function applyNumeralSet(
        PassageModel $passage,
        string $languageCodeHL
    ): void {
        if ($languageCodeHL === '') {
            return;
        }

        $numeralSet = $this->languageRepo
            ->getNumeralSetByLanguageCodeHl($languageCodeHL);

        if (!$numeralSet) {
            return; // nothing to convert
        }

        $ref = $passage->getReferenceLocalLanguage();
        if ($ref === '') {
            return;
        }

        $converted = $this->numeralConverter
            ->convertReference($ref, $numeralSet);

        $passage->setReferenceLocalLanguage($converted);
    }
}
