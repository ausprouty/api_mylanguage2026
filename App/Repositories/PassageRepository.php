<?php

namespace App\Repositories;

use App\Factories\PassageFactory;
use App\Models\Bible\PassageModel;
use App\Services\Database\DatabaseService;
use App\Services\LoggerService;

use PDO;

/**
 * Repository for handling Bible passage records in the database.
 */
class PassageRepository extends BaseRepository
                        implements PassageRepositoryInterface
{
    /**
     * @var DatabaseService The service for interacting with the database.
     */
  

    /**
     * Constructor to initialize the repository with a database service.
     *
     * @param DatabaseService $databaseService The database service instance.
     */
    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    /**
     * Checks if a Bible passage exists by its ID.
     *
     * @param string $bpid The ID of the Bible passage.
     * @return bool True if the passage exists, false otherwise.
     */
    public function existsById(string $bpid): bool
    {
        $query = 'SELECT bpid FROM bible_passages WHERE bpid = :bpid LIMIT 1';
        $params = [':bpid' => $bpid];
        $results = $this->databaseService->executeQuery($query, $params);
        $output =  (bool) $results->fetch(PDO::FETCH_OBJ);
        return $output;
    }
    public function findByBpid(string $bpid): ?PassageModel
    {
        return $this->findStoredById($bpid);
    }
    /**
     * Finds a stored Bible passage by its ID.
     *
     * @param string $bpid The ID of the Bible passage.
     * @return PassageModel|null The Bible passage, or null if not found.
     */
    public function findStoredById(string $bpid): ?PassageModel
    {
        $query = 'SELECT * FROM bible_passages WHERE bpid = :bpid LIMIT 1';
        $params = [':bpid' => $bpid];

        try {
            $results = $this->databaseService->executeQuery($query, $params);
            $data = $results->fetch(PDO::FETCH_OBJ);
            LoggerService::logDebug('[PassageRepository -- findStoredById]',[
                'data'=> $data
            ]);
            if ($data) {
                $biblePassage = PassageFactory::createFromData($data);
                LoggerService::logDebug('[PassageRepository -- findStoredById]',[
                'biblePassage'=> $biblePassage
            ]);
                return $biblePassage;
            }
        } catch (\Exception $e) {
            error_log("Error fetching Bible passage: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Inserts a new Bible passage record into the database.
     *
     * @param PassageModel $biblePassage The Bible passage to insert.
     */
    private function insertPassageRecord(PassageModel $biblePassage): void
    {
        $query = 'INSERT INTO bible_passages 
                  (bpid, referenceLocalLanguage, passageText, passageUrl, 
                   dateLastUsed, dateChecked, timesUsed)
                  VALUES 
                  (:bpid, :referenceLocalLanguage, :passageText, :passageUrl, 
                   :dateLastUsed,:dateChecked, :timesUsed)';
        $params = [
            ':bpid' => $biblePassage->getBpid(),
            ':referenceLocalLanguage' => $biblePassage->getReferenceLocalLanguage(),
            ':passageText' => $biblePassage->getPassageText(),
            ':passageUrl' => $biblePassage->getPassageUrl(),
            ':dateLastUsed' => date("Y-m-d"),
            ':dateChecked'=> $biblePassage->getDateChecked(),
            ':timesUsed' => $biblePassage->getTimesUsed()
        ];

        $this->databaseService->executeQuery($query, $params);
    }

    /**
     * Saves a Bible passage record, updating it if it already exists.
     *
     * @param PassageModel $biblePassage The Bible passage to save.
     */
    public function savePassageRecord(PassageModel $biblePassage): void
    {
        if ($this->existsById($biblePassage->getBpid())) {

            $this->updatePassageRecord($biblePassage);
        } else {
            $this->insertPassageRecord($biblePassage);
        }
    }

    /**
     * Updates an existing Bible passage record in the database.
     *
     * @param PassageModel $biblePassage The Bible passage to update.
     */
    private function updatePassageRecord(PassageModel $biblePassage): void
    {
        $query = 'UPDATE bible_passages
                  SET referenceLocalLanguage = :referenceLocalLanguage, 
                      passageText = :passageText, 
                      passageUrl = :passageUrl
                  WHERE bpid = :bpid LIMIT 1';
        $params = [
            ':referenceLocalLanguage' => $biblePassage->getReferenceLocalLanguage(),
            ':passageText' => $biblePassage->getPassageText(),
            ':passageUrl' => $biblePassage->getPassageUrl(),
            ':bpid' => $biblePassage->getBpid()
        ];

        $this->databaseService->executeQuery($query, $params);
    }

    /**
     * Persists the usage statistics for a Bible passage.
     *
     * @param PassageModel $biblePassage The Bible passage to update.
     */
    public function updatePassageUse(PassageModel $biblePassage): void
    {
        $query = 'UPDATE bible_passages
                  SET dateLastUsed = :dateLastUsed, timesUsed = :timesUsed
                  WHERE bpid = :bpid LIMIT 1';
        $params = [
            ':dateLastUsed' => $biblePassage->getDateLastUsed(),
            ':timesUsed' => $biblePassage->getTimesUsed(),
            ':bpid' => $biblePassage->getBpid()
        ];

        $this->databaseService->executeQuery($query, $params);
    }
}
