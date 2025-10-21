<?php

namespace App\Repositories;

use App\Services\Database\DatabaseService;
use App\Models\Language\DbsLanguageModel;
use App\Configuration\Config;

class DbsLanguageRepository extends BaseRepository
{

    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    /**
     * Save the DbsLanguageModel to the database.
     * Inserts a new record if it doesn't exist; updates it if it does.
     *
     * @param DbsLanguageModel $dbsLanguage
     */
    public function save(DbsLanguageModel $dbsLanguage)
    {
        if ($this->recordExists($dbsLanguage->getLanguageCodeHL())) {
            $this->updateRecord($dbsLanguage);
        } else {
            $this->insertRecord($dbsLanguage);
        }
    }

    /**
     * Checks if a record exists by languageCodeHL.
     *
     * @param string $languageCodeHL
     * @return bool
     */
    private function recordExists(string $languageCodeHL): bool
    {
        $query = "SELECT languageCodeHL FROM dbs_languages WHERE languageCodeHL = :code LIMIT 1";
        $params = [':code' => $languageCodeHL];
        return (bool) $this->databaseService->fetchSingleValue($query, $params);
    }

    /**
     * Inserts a new record into dbs_languages.
     *
     * @param DbsLanguageModel $dbsLanguage
     */
    private function insertRecord(DbsLanguageModel $dbsLanguage): void
    {
        $query = "INSERT INTO dbs_languages (languageCodeHL, collectionCode, format)
                  VALUES (:languageCodeHL, :collectionCode, :format)";
        $params = [
            ':languageCodeHL' => $dbsLanguage->getLanguageCodeHL(),
            ':collectionCode' => $dbsLanguage->getCollectionCode(),
            ':format' => $dbsLanguage->getFormat()
        ];
        $this->databaseService->executeQuery($query, $params);
    }

    /**
     * Updates an existing record in dbs_languages.
     *
     * @param DbsLanguageModel $dbsLanguage
     */
    private function updateRecord(DbsLanguageModel $dbsLanguage): void
    {
        $query = "UPDATE dbs_languages
                  SET collectionCode = :collectionCode, format = :format
                  WHERE languageCodeHL = :languageCodeHL
                  LIMIT 1";
        $params = [
            ':collectionCode' => $dbsLanguage->getCollectionCode(),
            ':format' => $dbsLanguage->getFormat(),
            ':languageCodeHL' => $dbsLanguage->getLanguageCodeHL()
        ];
        $this->databaseService->executeQuery($query, $params);
    }

    public function getLanguagesWithCompleteBible(){
        $query = "SELECT * FROM dbs_languages  as d
            INNER JOIN hl_languages as h
            ON d.languageCodeHL = h.languageCodeHL
            WHERE d.collectionCode = :collectionCode";
        $params = [':collectionCode' =>'C'];
        $result = $this->databaseService->fetchAll($query,$params);
        return $result;
    }
    public function getSummaryOfLanguagesWithCompleteBible(){
        $query = "SELECT h.id, h.name, h.ethnicName,
                 h.languageCodeIso, h.languageCodeHL, h.languageCodeJF, h.isChinese
          FROM hl_languages AS h
          INNER JOIN dbs_languages AS d
          ON d.languageCodeHL = h.languageCodeHL
          WHERE d.collectionCode = :collectionCode
          ORDER BY h.name";
        $params = [':collectionCode' => 'C'];
        $result = $this->databaseService->fetchAll($query, $params);

        $output = [];
        $translation_dir = Config::getDir('resources.translations') . 'languages/';

        foreach ($result as $language) {
            if ($language['isChinese'] == 1){
                $language['languageCodeHL'] = 'chn-s';
            }
            if (file_exists($translation_dir . $language['languageCodeHL'])) {
                $output[] = $language;
            }
        }

        return $output;
    }
    function getSummaryOfLanguagesForDBSAndJVideo(){
        $query = "SELECT h.id, h.name, h.ethnicName,
            h.languageCodeIso, h.languageCodeHL, h.languageCodeJF, h.isChinese
        FROM hl_languages AS h
        INNER JOIN dbs_languages AS d
        ON d.languageCodeHL = h.languageCodeHL
        WHERE d.collectionCode = :collectionCode
        AND TRIM(h.languageCodeJF) != ''  -- Fix for empty strings
        ORDER BY h.name";
    
        $params = [':collectionCode' => 'C'];
        $result = $this->databaseService->fetchAll($query, $params);
    
        error_log("Records found: " . count($result));
       
    
        $output = [];
        $translation_dir = Config::getDir('resources.translations') . 'languages/';
    
        foreach ($result as $language) {
            if ($language['isChinese'] == 1){
                $language['languageCodeHL'] = 'chn-s';
            }
            error_log("Checking: " . $translation_dir . $language['languageCodeHL']);
            if (file_exists($translation_dir . $language['languageCodeHL'])) {
                error_log("File exists for: " . $language['languageCodeHL']);
                $output[] = $language;
            } else {
                error_log("Missing file: " . $language['languageCodeHL']);
            }
        }
        return $output;
    }
    

}
