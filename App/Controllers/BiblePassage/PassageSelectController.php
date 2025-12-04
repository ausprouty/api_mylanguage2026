<?php

declare(strict_types=1);

namespace App\Controllers\BiblePassage;

use App\Controllers\BiblePassage\BibleBrain\BibleBrainTextPlainController;
use App\Controllers\BiblePassage\BibleGateway\BibleGatewayPassageController;
use App\Controllers\BiblePassage\BibleWordPassageController;
use App\Controllers\BiblePassage\BibleYouVersionPassageController;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Repositories\PassageRepository;
use App\Repositories\LanguageRepository;
use App\Services\BiblePassage\ReferenceLocaliserService;
use App\Services\Database\DatabaseService;
use App\Models\Language\LanguageModel;


class PassageSelectController
{
    private LanguageRepository $languageRepository;
    private DatabaseService $databaseService;
    private PassageReferenceModel $bibleReference;
    private BibleModel $bible;
    private PassageRepository $passageRepository;
    private ReferenceLocaliserService $referenceLocaliser;

    private int $passageId;
    public string $passageText = '';
    public string $passageUrl = '';
    public string $referenceLocalLanguage = ' ';

    public function __construct(
        DatabaseService $databaseService,
        PassageReferenceModel $bibleReference,
        BibleModel $bible,
        LanguageRepository $languageRepository,
        PassageRepository $passageRepository,
        ReferenceLocaliserService $referenceLocaliser
    ) {
        $this->databaseService       = $databaseService;
        $this->bibleReference        = $bibleReference;
        $this->bible                 = $bible;
        $this->languageRepository    = $languageRepository;
        $this->passageRepository     = $passageRepository;
        $this->referenceLocaliser    = $referenceLocaliser;
    }

    public function loadPassage(): void
    {
        $bpid = $this->bibleReference->getBpid();
        $this->passageId = 0; // or whatever you already do

        // 1) Try to load existing passage from DB
        $existing = $this->passageRepository
            ->findByBpid($bpid);

        if ($existing instanceof PassageModel
            && $existing->getPassageText() !== '') {
            // We already have a stored, complete passage.
            $this->hydrateFromModel($existing);
            return;
        }

        // 2) If not in DB, fetch from the appropriate external source
        $passage = $this->fetchExternalPassage();

        // Ensure bpid is set on the model
        $passage->setBpid($bpid);

        // 3) Apply numeral localisation
        $hl = $this->bible->getLanguageCodeHL();
        if ($hl !== '') {
            $this->referenceLocaliser->applyNumeralSet($passage, $hl);
        }

        // 4) Save to DB
        $this->passageRepository->savePassageRecord($passage);

        // 5) Expose values for caller / view
        $this->hydrateFromModel($passage);
    }


    private function fetchExternalPassage()
    {
        switch ($this->bible->getSource()) {
            case 'bible_brain':
                $controller = new BibleBrainTextPlainController($this->bibleReference, $this->bible);
                 return $controller->fetchPassage();

            case 'bible_gateway':
                $controller = new BibleGatewayPassageController($this->bibleReference, $this->bible);
                 return $controller->fetchPassage();

            case 'youversion':
                $controller = new BibleYouVersionPassageController($this->bibleReference, $this->bible);
                 return $controller->fetchPassage();

            case 'word':
                $controller = new BibleWordPassageController($this->bibleReference, $this->bible);
                 return $controller->fetchPassage();

            default:
                return new PassageModel();
        }
    }

    private function hydrateFromModel(PassageModel $passage): void
    {
        $this->passageText            = $passage->getPassageText();
        $this->passageUrl             = $passage->getPassageUrl();
        $this->referenceLocalLanguage = $passage->getReferenceLocalLanguage();
    }


    private function setDefaultPassage()
    {
        $this->passageText = '';
        $this->passageUrl = '';
        $this->referenceLocalLanguage = ' ';
    }

    private function applyTextDirection()
    {
        if (empty($this->passageText)) {
            return;
        }

        $direction = $this->bible->getDirection() ?: $this->determineDirection();
        $this->passageText = sprintf('<div dir="%s">%s</div>', $direction, $this->passageText);
    }

    private function determineDirection()
    {
        $languageCodeHL = $this->bible->getLanguageCodeHL();
        $language = new LanguageModel($this->languageRepository);
        $language->findOneLanguageByLanguageCodeHL($languageCodeHL);

        $direction = $language->getDirection() ?: 'ltr';
        $this->updateDirectionInDatabase($languageCodeHL, $direction);

        return $direction;
    }

    private function updateDirectionInDatabase($languageCodeHL, $direction)
    {
        $query = "UPDATE bibles SET direction = :direction WHERE languageCodeHL = :languageCodeHL";
        $params = [
            ':languageCodeHL' => $languageCodeHL,
            ':direction' => $direction
        ];
        $this->databaseService->executeQuery($query, $params);
    }

   
}
