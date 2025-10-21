<?php

namespace App\Controllers\Language;

use App\Services\Database\DatabaseService;
use App\Responses\JsonResponse;
use PDO as PDO;
use Exception;


class HindiLanguageController{

    protected $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    public function webGetLanguageOptions(){
        $output = $this->getLanguageOptions();
        JsonResponse::success($output);
    }

    public function getLanguageOptions(){
        $query = "SELECT *
                  FROM hl_languages
                  WHERE isHindu  = 'Y'
                  ORDER BY name";
        try {
            $results = $this->databaseService->executeQuery($query);
            $output = $results->fetchAll(PDO::FETCH_ASSOC);
            return $output;
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
    
}