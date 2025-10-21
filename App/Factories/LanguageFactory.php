<?php

namespace App\Factories;

use App\Models\Language\LanguageModel;
use App\Services\Database\DatabaseService;

/**
 * Factory for creating and populating LanguageModel instances.
 */
class LanguageFactory
{
    private $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    /**
     * Creates a LanguageModel and populates it with provided data.
     */
    public function create(array $data): LanguageModel
    {
        $model = new LanguageModel();
        $model->populate($data);
        return $model;
    }

    

    

    
}
