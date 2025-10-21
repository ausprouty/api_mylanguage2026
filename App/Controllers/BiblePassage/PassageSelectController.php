<?php

namespace App\Controllers\BiblePassage;

use App\Controllers\BiblePassage\BibleYouVersionPassageController;
use App\Controllers\BiblePassage\BibleWordPassageController;
use App\Controllers\BiblePassage\BibleBrain\BibleBrainTextPlainController;
use App\Controllers\BiblePassage\BibleGateway\BibleGatewayPassageController;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Services\Database\DatabaseService;
use App\Models\Language\LanguageModel;
use App\Repositories\LanguageRepository;

class PassageSelectController
{
    private $languageRepository;
    private $databaseService;
    private $bibleReference;
    private $bible;
    private $passageId;
    public $passageText;
    public $passageUrl;
    public $referenceLocalLanguage;

    public function __construct(
        DatabaseService $databaseService,
        PassageReferenceModel $bibleReference,
        BibleModel $bible,
        LanguageRepository $languageRepository
    ) {
        $this->databaseService = $databaseService;
        $this->bibleReference = $bibleReference;
        $this->languageRepository = $languageRepository;
        $this->bible = $bible;
        $this->checkDatabase();
    }

    public function getBible()
    {
        return $this->bible;
    }

    public function getBibleDirection()
    {
        return $this->bible->getDirection();
    }

    public function getBibleBid()
    {
        return $this->bible->getBid();
    }

    public function getBibleReference()
    {
        return $this->bibleReference;
    }

    private function checkDatabase()
    {
        $this->passageId = PassageModel::createBiblePassageId($this->bible->getBid(), $this->bibleReference);
        $passage = new PassageModel();
        $passage->findStoredById($this->passageId);

        if ($passage->getReferenceLocalLanguage()) {
            $this->passageText = $passage->getPassageText();
            $this->passageUrl = $passage->getPassageUrl();
            $this->referenceLocalLanguage = $passage->getReferenceLocalLanguage();
        } else {
            $this->retrieveExternalPassage();
        }

        $this->applyTextDirection();
    }

    private function retrieveExternalPassage()
    {
        switch ($this->bible->getSource()) {
            case 'bible_brain':
                $passage = new BibleBrainTextPlainController($this->bibleReference, $this->bible);
                break;
            case 'bible_gateway':
                $passage = new BibleGatewayPassageController($this->bibleReference, $this->bible);
                break;
            case 'youversion':
                $passage = new BibleYouVersionPassageController($this->bibleReference, $this->bible);
                break;
            case 'word':
                $passage = new BibleWordPassageController($this->bibleReference, $this->bible);
                break;
            default:
                $this->setDefaultPassage();
                return;
        }

        $this->passageText = $passage->getPassageText();
        $this->passageUrl = $passage->getPassageUrl();
        $this->referenceLocalLanguage = $passage->getReferenceLocalLanguage();

        PassageModel::savePassageRecord($this->passageId, $this->referenceLocalLanguage, $this->passageText, $this->passageUrl);
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
