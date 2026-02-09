<?php

namespace App\Repositories;

use App\Models\Language\LanguageModel;
use App\Services\Database\DatabaseService;

/**
 * Handles BibleBrain-specific interactions with the hl_languages table,
 * such as syncing language metadata and flags.
 */
class BibleBrainLanguageRepository extends BaseRepository
{
    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    /**
     * Inserts a new language record using BibleBrain data.
     */
    public function createLanguageFromBibleBrainRecord(LanguageModel $language): void
    {
        $query = 'INSERT INTO hl_languages (languageCodeBibleBrain, languageCodeIso, name, ethnicName) 
                  VALUES (:languageCodeBibleBrain, :languageCodeIso, :name, :ethnicName)';
        $params = [
            ':languageCodeBibleBrain' => $language->getLanguageCodeBibleBrain(),
            ':languageCodeIso' => $language->getLanguageCodeIso(),
            ':name' => $language->getName(),
            ':ethnicName' => $language->getEthnicName()
        ];
        $this->databaseService->executeQuery($query, $params);
    }

    
    /**
     * Fetches the next language needing BibleBrain verification.
     * Only languages with a valid BibleBrain code and unverified in the last 6 months.
     */
    public function getNextLanguageForBibleBrainSync(): ?array
    {
        //TODO: change interval back to 6 months.
        $query = 'SELECT languageCodeHL, languageCodeIso, languageCodeBibleBrain
                  FROM hl_languages
                  WHERE languageCodeBibleBrain IS NOT NULL
                    AND (checkedBBBibles IS NULL OR checkedBBBibles < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                  ORDER BY languageCodeIso ASC
                  LIMIT 1';

        return $this->databaseService->fetchRow($query);
    }

      /**
     * Updates checkedBBBibles date to today for a given ISO code.
     */
    public function markLanguageAsChecked(string $languageCodeIso): void
    {
        $query = 'UPDATE hl_languages SET checkedBBBibles = CURDATE() WHERE languageCodeIso = :iso';
        $this->databaseService->executeQuery($query, [':iso' => $languageCodeIso]);
    }

   /** 
     * Clears the checkedBBBibles field in hl_languages 
     * if it is more than 4 months old.
     */
    public function clearCheckedBBBibles(): void 
    {
        $query = '
            UPDATE hl_languages
            SET checkedBBBibles = NULL
            WHERE checkedBBBibles IS NOT NULL
            AND checkedBBBibles < DATE_SUB(CURDATE(), INTERVAL 4 MONTH)
        ';
        $this->databaseService->executeQuery($query);
    }
    /**
     * Retrieves HL and BibleBrain language codes from ISO code.
     */
    public function getLanguageCodesFromBibleBrain(string $languageCodeBibleBrain): ?array
    {
        $query = 'SELECT languageCodeHL, languageCodeJF
                  FROM hl_languages
                  WHERE languageCodeBibleBrain = :languageCodeBibleBrain LIMIT 1';
        return $this->databaseService->fetchRow($query, [':languageCodeBibleBrain' => $languageCodeBibleBrain]);
    }

    /**
     * Updates the BibleBrain code for a language by ISO code.
     */
    public function updateLanguageCodeBibleBrain(string $languageCodeIso, string $languageCodeBibleBrain): void
    {
        $query = 'UPDATE hl_languages 
                  SET languageCodeBibleBrain = :languageCodeBibleBrain 
                  WHERE languageCodeIso = :languageCodeIso
                  AND languageCodeBibleBrain IS NULL';
        $params = [
            ':languageCodeBibleBrain' => $languageCodeBibleBrain,
            ':languageCodeIso' => $languageCodeIso
        ];
        $this->databaseService->executeQuery($query, $params);
    }

    /**
     * Checks if a BibleBrain language ID exists in the database.
     */
    public function bibleBrainLanguageRecordExists(string $languageCodeBibleBrain): bool
    {
        $query = 'SELECT id FROM hl_languages 
                  WHERE languageCodeBibleBrain = :languageCodeBibleBrain 
                  LIMIT 1';
        return $this->databaseService->fetchSingleValue(
            $query,
            [':languageCodeBibleBrain' => $languageCodeBibleBrain]
        ) !== null;
    }


    /**
     * Retrieves the next languageCodeIso needing BibleBrain detail processing.
     * Will recheck every three months.
     */
    public function getNextLanguageForLanguageDetails(): ?string
    {
         $query = '
        SELECT languageCodeIso
        FROM hl_languages
        WHERE
            checkedBBBibles IS NULL
            OR checkedBBBibles < DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        ORDER BY
            checkedBBBibles ASC
        LIMIT 1
    ';
        return $this->databaseService->fetchColumn($query);
    }
}
