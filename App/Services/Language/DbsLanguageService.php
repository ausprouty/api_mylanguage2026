<?php

namespace App\Services\Language;

use App\Repositories\DbsLanguageRepository;
use App\Configuration\Config;
use App\Controllers\BibleController;
use App\Services\LoggerService;
use App\Models\Language\DbsLanguageModel;

class DbsLanguageService
{
    protected $dbsLanguageRepository;
    protected $bibleController;

    public function __construct(DbsLanguageRepository $dbsLanguageRepository, BibleController $bibleController)
    {
        $this->dbsLanguageRepository = $dbsLanguageRepository;
        $this->bibleController = $bibleController;
    }
    public function processLanguageFiles()
    {
        $directory = Config::getDir('resources.translations') .  'languages/';
        $scannedDirectory = array_diff(scandir($directory), ['..', '.']);
        foreach ($scannedDirectory as $languageCodeHL) {
            $bible = $this->bibleController->getBestBibleByLanguageCodeHL($languageCodeHL);
            if (!$bible || $bible['weight'] != 9) {
                continue;
            }
            $format = ($bible['source'] === 'youversion') ? 'link' : 'text';
            $collectionCode = $bible['collectionCode'];

            // Create DbsLanguageModel (or persist using repository)
            $dbs = new DbsLanguageModel($languageCodeHL, $collectionCode, $format);
        }
    }

    public function fetchLanguageOptions()
    {
        return $this->dbsLanguageRepository->getLanguagesWithCompleteBible();
    }
}
