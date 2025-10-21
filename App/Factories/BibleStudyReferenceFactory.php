<?php

namespace App\Factories;

use App\Models\BibleStudy\StudyReferenceModel;
use App\Repositories\PassageReferenceRepository;
use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use App\Configuration\Config;
use Exception;

/**
 * Factory class for creating and populating StudyReferenceModel instances.
 * Automatically parses and completes passage reference metadata if missing.
 */
class BibleStudyReferenceFactory
{
    private DatabaseService $databaseService;
    private PassageReferenceRepository $passageReferenceRepository;

    public function __construct(
        DatabaseService $databaseService,
        PassageReferenceRepository $passageReferenceRepository
    ) {
        $this->databaseService = $databaseService;
        $this->passageReferenceRepository = $passageReferenceRepository;
    }

    /**
     * Creates and populates a StudyReferenceModel for a given study and lesson.
     *
     * @param string $study
     * @param int $lesson
     * @return StudyReferenceModel
     * @throws Exception if the study/lesson is not found.
     */
    public function createModel(string $study, int $lesson): StudyReferenceModel
    {
        $query = "SELECT * FROM study_references WHERE study = :study AND lesson = :lesson";
        $params = [':study' => $study, ':lesson' => $lesson];
        $data = $this->databaseService->fetchRow($query, $params);

        if (empty($data)) {
            throw new Exception("No record found for study '$study' and lesson $lesson.");
        }

        $result = $this->expandPassageReferenceInfo($data);
        $missing = $this->validatePassageData($result);

        if ($missing) {
            $result = $this->populateMissingValues($result);
            $this->updateStudyDatabase($study, $lesson, $result);
        }

        $model = (new StudyReferenceModel())->populate($result);
        $model->setStudy($study);
        return $model;
    }

    /**
     * Decodes and expands the JSON field `passageReferenceInfo` into individual array keys.
     *
     * @param array $reference
     * @return array
     */
    protected function expandPassageReferenceInfo(array $reference): array
    {
        $json = json_decode($reference['passageReferenceInfo'] ?? '', true);

        if (!$json) {
            error_log('Failed to decode passageReferenceInfo: ' .
                ($reference['passageReferenceInfo'] ?? 'NULL') .
                '. Error: ' . json_last_error_msg());
            $json = [];
        }

        return array_merge($reference, [
            'bookID' => $json['bookID'] ?? null,
            'bookName' => $json['bookName'] ?? null,
            'bookNumber' => $json['bookNumber'] ?? 0,
            'chapterStart' => $json['chapterStart'] ?? null,
            'chapterEnd' => $json['chapterEnd'] ?? null,
            'verseStart' => $json['verseStart'] ?? null,
            'verseEnd' => $json['verseEnd'] ?? null,
            'passageID' => $json['passageID'] ?? null,
            'uversionBookID' => $json['uversionBookID'] ?? null,
        ]);
    }

    /**
     * Checks for any required fields that are empty or null.
     *
     * @param array $data
     * @return array List of missing field names.
     */
    protected function validatePassageData(array $data): array
    {
        $requiredFields = [
            'bookName', 'bookID', 'uversionBookID', 'bookNumber',
            'testament', 'chapterStart', 'verseStart', 'verseEnd', 'passageID'
        ];

        return array_filter($requiredFields, fn($field) => empty($data[$field]));
    }

    public function normalizeBookName(string $bookName, string $languageCodeHL = 'eng00'): string
    {
        $aliases = $this->loadBookAliases($languageCodeHL);

        foreach ($aliases as $official => $synonyms) {
            if (strcasecmp($bookName, $official) === 0 || in_array($bookName, $synonyms, true)) {
                return $official;
            }
        }

        return $bookName; // fallback if no match
    }


    /**
     * Parses the `reference` string and populates all missing passage-related metadata.
     *
     * @param array $data
     * @return array
     * @throws \RuntimeException if parsing fails or data cannot be found.
     */
    protected function populateMissingValues(array $data): array
    {
        $parsed = $this->parseReference($data['reference']);
        if (!$parsed) {
            throw new \RuntimeException("Unable to parse reference: " . $data['reference']);
        }
        $data = array_merge($data, $parsed);
        $data['bookID'] = $this->passageReferenceRepository->findBookID($parsed['bookName']);
        if (!$data['bookID']) {
            throw new \RuntimeException("Could not find book ID for " . $parsed['bookName']);
        }
        $data['bookNumber'] = $this->passageReferenceRepository->findBookNumber($data['bookID']);
        $data['testament'] = $this->passageReferenceRepository->findTestament($data['bookID']);
        $data['uversionBookID'] = $this->passageReferenceRepository->findUversionBookID($data['bookID']);

        // Compose a unique passage identifier (e.g., MAT-28-19-20)
        $data['passageID'] = sprintf(
            '%s-%d-%d-%d',
            $data['bookID'],
            $parsed['chapterStart'],
            $parsed['verseStart'] ?? 1,
            $parsed['verseEnd'] ?? ($parsed['verseStart'] ?? 1)
        );

        return $data;
    }

    /**
     * Parses a Bible reference string like "Luke 14:25–33" into its components.
     *
     * @param string $reference
     * @return array|null
     */
    protected function parseReference(string $reference): ?array
    {
        // Normalize spacing: e.g., "Luke14:25–33" → "Luke 14:25–33"
        $reference = preg_replace('/([a-zA-Z])(\d)/', '$1 $2', $reference);

        // Pattern:
        // 1. Book name (with spaces)
        // 2. Chapter start
        // 3. Verse start (optional)
        // 4. Chapter end (optional)
        // 5. Verse end (optional)

        if (!preg_match('/^([\dA-Za-z ]+)\s+(\d+)(?::(\d+))?(?:[-–](?:(\d+):)?(\d+))?$/u', $reference, $matches)) { 
            error_log("❌ Failed to parse reference: $reference");
            return null;
        }

        $bookName     = trim($matches[1]);
        $chapterStart = (int)$matches[2];
        $verseStart   = isset($matches[3]) && $matches[3] !== '' ? (int)$matches[3] : null;
        $chapterEnd   = isset($matches[4]) && $matches[4] !== '' ? (int)$matches[4] : $chapterStart;
        $verseEnd     = isset($matches[5]) && $matches[5] !== '' ? (int)$matches[5] : $verseStart;

        return compact('bookName', 'chapterStart', 'chapterEnd', 'verseStart', 'verseEnd');
    }


    /**
     * Updates the study_references table with the newly built passageReferenceInfo JSON.
     *
     * @param string $study
     * @param int $lesson
     * @param array $data
     */
    protected function updateStudyDatabase(string $study, int $lesson, array $data): void
    {
        $json = $this->buildPassageReferenceInfoJson($data);

        $query = "UPDATE study_references
                  SET passageReferenceInfo = :passageReferenceInfo
                  WHERE study = :study AND lesson = :lesson";

        $params = [
            ':passageReferenceInfo' => $json,
            ':study' => $study,
            ':lesson' => $lesson,
        ];

        $this->databaseService->executeQuery($query, $params);
    }

    /**
     * Builds a JSON string containing the structured passage data for database storage.
     *
     * @param array $data
     * @return string
     */
    protected function buildPassageReferenceInfoJson(array $data): string
    {
        return json_encode([
            'bookID' => $data['bookID'] ?? null,
            'bookName' => $data['bookName'] ?? null,
            'bookNumber' => $data['bookNumber'] ?? 0,
            'chapterStart' => $data['chapterStart'] ?? 0,
            'chapterEnd' => $data['chapterEnd'] ?? 0,
            'verseStart' => $data['verseStart'] ?? 0,
            'verseEnd' => $data['verseEnd'] ?? 0,
            'passageID' => $data['passageID'] ?? null,
            'uversionBookID' => $data['uversionBookID'] ?? null,
        ]);
    }

    public function loadBookAliases(string $languageCodeHL = 'eng00'): array
    {
       $path = rtrim(Config::getDir('resources.root'), '/\\') . "/bookAliases/{$languageCodeHL}.json";

        if (!file_exists($path)) {
            throw new \RuntimeException("Alias file not found: $path");
        }

        $json = json_decode(file_get_contents($path), true);

        if (!is_array($json)) {
            throw new \RuntimeException("Invalid alias JSON in $path");
        }

        return $json;
    }


    
}
