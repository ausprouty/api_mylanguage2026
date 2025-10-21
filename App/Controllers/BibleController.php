<?php

namespace App\Controllers;
use App\Models\BibleModel;
use App\Repositories\BibleRepository;
use App\Responses\JsonResponse;


class BibleController
{
    private $bibleRepository;

    public function __construct(BibleRepository $bibleRepository)
    {
        $this->bibleRepository = $bibleRepository;
    }

    public function getBestBibleByLanguageCodeHL(string $languageCode) : BibleModel
    {
        $bibleModel =  $this->bibleRepository->findBestBibleByLanguageCodeHL($languageCode);
        return $bibleModel;
    }
    public function webGetBestBibleByLanguageCodeHL(string $languageCode) :array
    {
        $bibleModel =  $this->bibleRepository->findBestBibleByLanguageCodeHL($languageCode);
        $output = $bibleModel->toArray();
        if (!is_array($output)) {
            $output = (array) $output;  // Ensure proper typecasting.
        }
        return JsonResponse::success($output);
    }
}
