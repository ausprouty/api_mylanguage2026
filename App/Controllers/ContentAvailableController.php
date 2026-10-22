<?php

namespace App\Controllers;

use App\Services\Database\DatabaseService;
use PDO;
use Throwable;

class ContentAvailableController
{
    private DatabaseService $databaseService;

    private bool $dbs = false;
    private bool $bilingualGospel = false;
    private bool $video = false;

    public ?string $languageCodeHL1;
    public ?string $languageCodeHL2;

    public function __construct(
        DatabaseService $databaseService,
        ?string $languageCodeHL1,
        ?string $languageCodeHL2 = null
    ) {
        $this->databaseService = $databaseService;
        $this->languageCodeHL1 = $languageCodeHL1;
        $this->languageCodeHL2 = $languageCodeHL2 ?? $languageCodeHL1;

        $this->setContentAvailable();
    }

    public function setContentAvailable(): void
    {
        $this->dbs = $this->getDbsAvailable();
        $this->bilingualGospel = $this->getBilingualTractAvailable();
        $this->video = $this->getVideoAvailable();
    }

    /**
     * @return array{
     *     dbs: bool,
     *     bilingualGospel: bool,
     *     video: bool
     * }
     */
    public function getAllOptions(): array
    {
        return [
            'dbs' => $this->dbs,
            'bilingualGospel' => $this->bilingualGospel,
            'video' => $this->video,
        ];
    }

    public function getDbsAvailable(): bool
    {
        return $this->checkAvailability(
            'SELECT languageCodeHL
             FROM dbs_languages
             WHERE languageCodeHL = :languageCodeHL1
                OR languageCodeHL = :languageCodeHL2
             LIMIT 1'
        );
    }

    public function getBilingualTractAvailable(): bool
    {
        return $this->checkAvailability(
            'SELECT languageCodeHL1
             FROM hl_bilingual_tracts
             WHERE languageCodeHL1 = :languageCodeHL1
                OR languageCodeHL1 = :languageCodeHL2
             LIMIT 1'
        );
    }

    public function getVideoAvailable(): bool
    {
        return $this->checkAvailability(
            'SELECT languageCodeHL
             FROM jesus_video_languages
             WHERE languageCodeHL = :languageCodeHL1
                OR languageCodeHL = :languageCodeHL2
             LIMIT 1'
        );
    }

    /**
     * Execute an availability query using the selected languages.
     */
    private function checkAvailability(string $query): bool
    {
        $params = [
            ':languageCodeHL1' => $this->languageCodeHL1,
            ':languageCodeHL2' => $this->languageCodeHL2,
        ];

        try {
            $results = $this->databaseService->executeQuery(
                $query,
                $params
            );

            return (bool) $results->fetch(PDO::FETCH_ASSOC);

        } catch (Throwable $e) {
            error_log(
                'ContentAvailableController: ' . $e->getMessage()
            );

            return false;
        }
    }
}

