<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Database\DatabaseService;

class BibleGatewayRepository extends BaseRepository
{
    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    public function recordExists(string $externalId): int|false
    {
        $query = "
            SELECT bid
            FROM bibles
            WHERE source = :source
              AND externalId = :externalId
            LIMIT 1
        ";

        $params = [
            ':source' => 'bible_gateway',
            ':externalId' => $externalId,
        ];

        $bid = $this->databaseService->fetchSingleValue(
            $query,
            $params
        );

        return $bid === false || $bid === null
            ? false
            : (int) $bid;
    }

    public function insertBibleRecord(array $data): void
    {
        $query = "
            INSERT INTO bibles (
                source,
                externalId,
                volumeName,
                languageName,
                languageCodeIso,
                collectionCode,
                format,
                text,
                weight,
                dateVerified
            )
            VALUES (
                :source,
                :externalId,
                :volumeName,
                :languageName,
                :languageCodeIso,
                :collectionCode,
                :format,
                :text,
                :weight,
                :dateVerified
            )
        ";

        $this->databaseService->executeQuery($query, $data);
    }

    public function updateVerified(int $bid): void
    {
        $query = "
            UPDATE bibles
            SET dateVerified = :dateVerified
            WHERE bid = :bid
            LIMIT 1
        ";

        $this->databaseService->executeQuery($query, [
            ':dateVerified' => date('Y-m-d'),
            ':bid' => $bid,
        ]);
    }

    public function updateLanguage(
        int $bid,
        string $languageCodeIso,
        string $languageName
    ): void {
        $query = "
            UPDATE bibles
            SET languageCodeIso = :languageCodeIso,
                languageName = :languageName
            WHERE bid = :bid
            LIMIT 1
        ";

        $this->databaseService->executeQuery($query, [
            ':languageCodeIso' => $languageCodeIso,
            ':languageName' => $languageName,
            ':bid' => $bid,
        ]);
    }

    public function updateBibleWeight(int $bid, int $weight): void
    {
        $query = "
            UPDATE bibles
            SET weight = :weight
            WHERE bid = :bid
            LIMIT 1
        ";

        $this->databaseService->executeQuery($query, [
            ':weight' => $weight,
            ':bid' => $bid,
        ]);
    }
}
