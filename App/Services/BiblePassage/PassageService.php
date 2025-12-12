<?php

declare(strict_types=1);

namespace App\Services\BiblePassage;

use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Repositories\PassageRepository;

class PassageService
{
    public function __construct(
        private PassageRepository $passageRepository,
        private PassageSourceResolver $sourceResolver,
        private ReferenceLocaliserService $referenceLocaliser
    ) {
    }

    public function getPassage(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): PassageModel {
        $bpid = $reference->getBpid();

        // 1) Check cache
        $existing = $this->passageRepository->findByBpid($bpid);

        if ($existing instanceof PassageModel &&
            $existing->getPassageText() !== '') {
            return $existing;
        }

        // 2) Fetch from external provider
        $source  = $this->sourceResolver->resolve($bible);
        $passage = $source->fetchPassage($bible, $reference);

        $passage->setBpid($bpid);

        // 3) Localisation (kept simple for English, but ready for later)
        $hl = $bible->getLanguageCodeHL() ?: '';
        if ($hl !== '') {
            $this->referenceLocaliser
                ->applyNumeralSet($passage, $hl);
        }

        // 4) Save and return
        $this->passageRepository->savePassageRecord($passage);

        return $passage;
    }
}
