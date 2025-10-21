<?php

namespace App\Controllers\Language;

use App\Services\Language\DbsLanguageService;
use Exception;

class DbsLanguageUpdateController {
    protected $dbsLanguageService;

    public function __construct(DbsLanguageService $dbsLanguageService) {
        $this->dbsLanguageService = $dbsLanguageService;
    }

    public function updateDatabase() {
        try {
            $this->dbsLanguageService->processLanguageFiles();
        } catch (Exception $e) {
            // Handle and log exception
            echo "Error: " . $e->getMessage();
        }
    }

}
