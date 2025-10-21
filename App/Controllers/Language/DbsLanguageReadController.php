<?php

namespace App\Controllers\Language;

use App\Repositories\DbsLanguageRepository;
use App\Responses\JsonResponse;
use Exception;

class DbsLanguageReadController
{
    protected $dbsLanguageRepository;

    public function __construct(DbsLanguageRepository $dbsLanguageRepository)
    {
        $this->dbsLanguageRepository = $dbsLanguageRepository;
    }

    public function getLanguagesWithCompleteBible()
    {
        return $this->dbsLanguageRepository->getLanguagesWithCompleteBible();
    }

    public function webGetLanguagesWithCompleteBible()
    {
        $output = $this->getLanguagesWithCompleteBible();
        JsonResponse::success($output);
    }

    public function webGetSummaryOfLanguagesWithCompleteBible()
    {
        $output = $this->dbsLanguageRepository->getSummaryOfLanguagesWithCompleteBible();
        JsonResponse::success($output);
    }

    public function webGetSummaryOfLanguagesWithCompleteBibleAndJVideo()
    {
        $output = $this->dbsLanguageRepository->getSummaryOfLanguagesForDBSAndJVideo();
        JsonResponse::success($output);
    }
}
