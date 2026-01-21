<?php

namespace App\Repositories;

use App\Services\Database\DatabaseService;
use App\Models\Bible\BibleModel;

class BibleRepository extends BaseRepository
{
    

    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService); // Call BaseRepository constructor
    }
    public function addBibleBrainBible(array $bibleData)
    {
        $query = 'SELECT bid FROM bibles WHERE externalId = :externalId';
        $params = [':externalId' => $bibleData['externalId']];
        
        $bid = $this->databaseService->fetchSingleValue($query, $params);

        if (!$bid) {
            $query = 'INSERT INTO bibles (source, externalId, volumeName, 
                      volumeNameAlt, languageCodeHL, languageName, 
                      languageEnglish, collectionCode, format, audio, 
                      text, video, dateVerified) 
                      VALUES (:source, :externalId, :volumeName, 
                      :volumeNameAlt, :languageCodeHL, :languageName, 
                      :languageEnglish, :collectionCode, :format, :audio, 
                      :text, :video, :dateVerified)';
            $this->databaseService->executeQuery($query, $bibleData);
        }
    }

    public function findBestBibleByLanguageCodeHL(
        string $languageCodeHL
    ): ?BibleModel {
        $sql = '
            SELECT b.*
            FROM bibles AS b
            WHERE b.languageCodeHL = :hl
            ORDER BY b.weight DESC
            LIMIT 1
        ';

        $params = [
            ':hl' => $languageCodeHL,
        ];

        return $this->fetchAndPopulateModel(
            $sql,
            $params,
            BibleModel::class
        );
    }

    public function findBestDbsBibleByLanguageCodeHL(
        string $languageCodeHL,
        string $testament = 'C'
    ): ?BibleModel {
         $sql = '
            SELECT b.*
            FROM bibles AS b
            WHERE b.languageCodeHL = :hl
            AND b.weight = 9
            AND (b.collectionCode = :complete OR b.collectionCode = :testament)
            ORDER BY
            CASE WHEN b.collectionCode = :complete THEN 1 ELSE 0 END DESC
            LIMIT 1
        ';

        $params = [
            ':hl'        => $languageCodeHL,
            ':complete'  => 'C',
            ':testament' => $testament,
        ];

        return $this->fetchAndPopulateModel(
            $sql,
            $params,
            BibleModel::class
        );
    }


    public function findBibleByBid($bid): ?BibleModel
    {
        $query = 'SELECT * FROM bibles WHERE bid = :bid LIMIT 1';
        $params = [':bid' => $bid];
        return $this->fetchAndPopulateModel($query, $params, BibleModel::class);
    }

    public function findBibleByExternalId($externalId): ?BibleModel
    {
        $query = 'SELECT * FROM bibles WHERE externalId = :externalId LIMIT 1';
        $params = [':externalId' => $externalId];
        return $this->fetchAndPopulateModel($query, $params, BibleModel::class);;
    }

    public function getAllBiblesByLanguageCodeHL($languageCodeHL)
    {
        $query = 'SELECT * FROM bibles WHERE languageCodeHL = :code 
                  ORDER BY volumeName';
        $params = [':code' => $languageCodeHL];
        return $this->databaseService->fetchAll($query, $params);
    }

    public function getTextBiblesByLanguageCodeHL($languageCodeHL)
    {
        $query = 'SELECT * FROM bibles WHERE languageCodeHL = :code 
                  AND format NOT LIKE :audio 
                  AND format NOT LIKE :video 
                  AND format != :usx 
                  AND format IS NOT NULL 
                  AND source != :dbt 
                  ORDER BY volumeName';
        $params = [
            ':code' => $languageCodeHL,
            ':audio' => 'audio%',
            ':video' => 'video%',
            ':usx' => 'text_usx',
            ':dbt' => 'dbt'
        ];
        return $this->databaseService->fetchAll($query, $params);
    }

    public function hasOldTestament(string $languageCodeHL): bool
    {
        $query = 'SELECT bid FROM bibles WHERE languageCodeHL = :languageCodeHL 
                  AND (collectionCode = :OT OR collectionCode = :AL 
                  OR collectionCode = :C) LIMIT 1';
        $params = [
            ':languageCodeHL' => $languageCodeHL,
            ':OT' => 'OT',
            ':AL' => 'AL',
            ':C' => 'C'
        ];
        return (bool) $this->databaseService->fetchSingleValue($query, $params);
    }

    public function updateWeight($bid, $weight)
    {
        $query = 'UPDATE bibles SET weight = :weight WHERE bid = :bid LIMIT 1';
        $params = [
            ':weight' => $weight,
            ':bid' => $bid
        ];
        return $this->databaseService->executeQuery($query, $params) 
               ? 'success' : null;
    }
}
