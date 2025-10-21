<?php

namespace App\Repositories;

use App\Services\Database\DatabaseService;
use App\Models\Language\CountryLanguageModel;
use PDO;
use Exception;

class CountryLanguageRepository extends BaseRepository
{
    

    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    public function getLanguagesWithContentForCountry($countryCode)
    {
        $query = "SELECT * FROM country_languages 
                  WHERE countryCode = :countryCode
                  AND languageCodeHL != :blank
                  GROUP BY languageCodeHL
                  ORDER BY languageNameEnglish";
        $params = [
            ':countryCode' => $countryCode,
            ':blank' => ''
        ];

        try {
            $data = $this->databaseService->fetchAll($query, $params);

            return array_map(function ($row) {
                return new CountryLanguageModel(
                    $row->countryCode,
                    $row->languageCodeHL,
                    $row->languageNameEnglish
                );
            }, $data);

        } catch (Exception $e) {
            error_log("Error fetching languages for country code $countryCode: " . $e->getMessage());
            return null;
        }
    }
}
