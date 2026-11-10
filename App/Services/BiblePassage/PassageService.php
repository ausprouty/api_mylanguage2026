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
        
        private ReferenceLocaliserService $referenceLocaliser
    ) {
    }

    public function getPassage(
        BibleModel $bible,
        PassageReferenceModel $reference,
        PassageModel $passage
    ): PassageModel {

        $bpid = $passage->getBpid();

        if ($bpid === '') {
            throw new \InvalidArgumentException(
                'PassageService: BPID must be set before fetching passage'
            );
        }

        // 1) Check database
        $existing = $this->passageRepository->findByBpid($bpid);

        if (
            $existing instanceof PassageModel &&
            $existing->getPassageText() !== ''
        ) {
            return $existing;
        }

        // 2) Fetch from external provider
        $source = $this->sourceResolver->resolve($bible);

        $fetchedPassage = $source->fetchPassage(
            $bible,
            $reference
        );

        // Ensure BPID is assigned
        $fetchedPassage->setBpid($bpid);

        // Do not save empty passages
        if ($fetchedPassage->getPassageText() === '') {
            return $fetchedPassage;
        }

        // 3) Apply numeral localisation
        $hl = $bible->getLanguageCodeHL() ?: '';

        if ($hl !== '') {
            $this->referenceLocaliser->applyNumeralSet(
                $fetchedPassage,
                $hl
            );
        }

        // 4) Save to database
        $this->passageRepository->savePassageRecord(
            $fetchedPassage
        );

        return $fetchedPassage;
    }
}
