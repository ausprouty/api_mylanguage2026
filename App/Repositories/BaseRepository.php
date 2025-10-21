<?php

namespace App\Repositories;

use App\Services\Database\DatabaseService;

abstract class BaseRepository
{
    protected DatabaseService $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    /**
     * Fetch a single row and populate the model.
     */
    protected function fetchAndPopulateModel(
            string $query, 
            array $params, 
            string $modelClass
    )
    {
        $data = $this->databaseService->fetchRow($query, $params);
        if (!empty($data)) {
            $model = new $modelClass();
            $model->populate($data);
            return $model;
        }
        return null;
    }
}
