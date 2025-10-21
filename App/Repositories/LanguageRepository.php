<?php

namespace App\Repositories;

use App\Factories\LanguageFactory;
use App\Models\Language\LanguageModel;
use App\Services\Database\DatabaseService;

/**
 * Handles database operations related to the hl_languages table,
 * including creation, lookup, and updates of language records.
 */
class LanguageRepository extends BaseRepository
{
    private LanguageFactory $languageFactory;

    public function __construct(
        DatabaseService $databaseService,
        LanguageFactory $languageFactory
    ) {
        parent::__construct($databaseService);
        $this->languageFactory = $languageFactory;
    }

    /**
     * Checks if a language with the given ISO code exists.
     */
    public function languageIsoRecordExists(string $languageCodeIso): bool
    {
        $query = 'SELECT id FROM hl_languages WHERE languageCodeIso = :languageCodeIso LIMIT 1';
        return $this->databaseService->fetchSingleValue($query, [':languageCodeIso' => $languageCodeIso]) !== null;
    }

    /**
     * Retrieves a LanguageModel by a source-specific code (e.g., HL, Iso).
     */
    public function findOneByCode(string $source, string $code): ?LanguageModel
    {
        $field = 'languageCode' . $source;
        $query = 'SELECT * FROM hl_languages WHERE ' . $field . ' = :id';
        return $this->fetchAndPopulateModel($query, [':id' => $code], LanguageModel::class);
    }

    /**
     * Retrieves a LanguageModel by its HL code.
     */
    public function findOneLanguageByLanguageCodeHL(string $code): ?LanguageModel
    {
        $query = 'SELECT * FROM hl_languages WHERE languageCodeHL = :id';
        return $this->fetchAndPopulateModel($query, [':id' => $code], LanguageModel::class);
    }

    /**
     * Retrieves ISO code from an HL code.
     */
    public function getCodeIsoFromCodeHL(string $languageCodeHL): ?string
    {
        $query = 'SELECT languageCodeIso FROM hl_languages WHERE languageCodeHL = :languageCodeHL LIMIT 1';
        return $this->databaseService->fetchSingleValue($query, [':languageCodeHL' => $languageCodeHL]);
    }

    /**
     * Retrieves Google code from an HL code.
     */
    public function getCodeGoogleFromCodeHL(string $languageCodeHL): ?string
    {
        $query = 'SELECT languageCodeGoogle FROM hl_languages WHERE languageCodeHL = :languageCodeHL LIMIT 1';
        return $this->databaseService->fetchSingleValue($query, [':languageCodeHL' => $languageCodeHL]);
    }

    /**
     * Retrieves English name by ISO code.
     */
    public function getEnglishNameForLanguageCodeIso(string $languageCodeIso): ?string
    {
        $query = 'SELECT name FROM hl_languages WHERE languageCodeIso = :languageCodeIso';
        return $this->databaseService->fetchSingleValue($query, [':languageCodeIso' => $languageCodeIso]);
    }

    /**
     * Retrieves English name by HL code.
     */
    public function getEnglishNameForLanguageCodeHL(string $languageCodeHL): ?string
    {
        $query = 'SELECT name FROM hl_languages WHERE languageCodeHL = :languageCodeHL';
        return $this->databaseService->fetchSingleValue($query, [':languageCodeHL' => $languageCodeHL]);
    }

    /**
     * Retrieves all unique ethnic names for a given ISO code.
     */
    public function getEthnicNamesForLanguageIso(string $languageCodeIso): array
    {
        $query = 'SELECT DISTINCT ethnicName
                  FROM hl_languages
                  WHERE languageCodeIso = :languageCodeIso AND ethnicName IS NOT NULL';
        
        return $this->databaseService->fetchColumn($query, [':languageCodeIso' => $languageCodeIso]);
    }

    /**
     * Retrieves ethnic name by ISO code.
     */
    public function getEthnicNameForLanguageCodeIso(string $languageCodeIso): ?string
    {
        $query = 'SELECT ethnicName FROM hl_languages WHERE languageCodeIso = :languageCodeIso LIMIT 1';
        return $this->databaseService->fetchSingleValue($query, [':languageCodeIso' => $languageCodeIso]);
    }

    /**
     * Retrieves font data by HL code.
     */
    public function getFontDataFromLanguageCodeHL(string $languageCodeHL): ?string
    {
        $query = 'SELECT fontData FROM hl_languages WHERE languageCodeHL = :languageCodeHL LIMIT 1';
        return $this->databaseService->fetchSingleValue($query, [':languageCodeHL' => $languageCodeHL]);
    }

    /**
     * Inserts a new language with a generated HL code based on ISO and current year.
     */
    public function insertLanguage(string $languageCodeIso, string $name): void
    {
        $languageCodeHL = $languageCodeIso . date('y');
        $query = 'INSERT INTO hl_languages (languageCodeIso, languageCodeHL, name)
                  VALUES (:languageCodeIso, :languageCodeHL, :name)';
        $params = [
            ':languageCodeIso' => $languageCodeIso,
            ':languageCodeHL' => $languageCodeHL,
            ':name' => $name
        ];
        $this->databaseService->executeQuery($query, $params);
    }

    /**
     * Retrieves HL and BibleBrain language codes from ISO code.
     */
    public function getLanguageCodesFromIso(string $languageCodeIso): ?array
    {
        $query = 'SELECT languageCodeHL, languageCodeBibleBrain
                  FROM hl_languages
                  WHERE languageCodeIso = :languageCodeIso LIMIT 1';
        return $this->databaseService->fetchRow($query, [':languageCodeIso' => $languageCodeIso]);
    }

    /**
     * Updates the ethnic name of a language by ISO code.
     */
    public function updateEthnicName(string $languageCodeIso, string $ethnicName): void
    {
        $query = 'UPDATE hl_languages SET ethnicName = :ethnicName WHERE languageCodeIso = :languageCodeIso';
        $params = [
            ':ethnicName' => $ethnicName,
            ':languageCodeIso' => $languageCodeIso
        ];
        $this->databaseService->executeQuery($query, $params);
    }
}
