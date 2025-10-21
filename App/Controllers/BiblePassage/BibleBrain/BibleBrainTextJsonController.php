<?php

namespace App\Controllers\BiblePassage\BibleBrain;

use App\Services\BiblePassage\BibleBrainPassageService;
use App\Services\Web\CloudFrontConnectionService;

class BibleBrainTextJsonController
{
    private $passageService;
    private $cloudFrontConnection;
    public $json;

    public function __construct(BibleBrainPassageService $passageService)
    {
        $this->passageService = $passageService;
    }

    /**
     * Fetch external passage data from BibleBrain and retrieve JSON data from CloudFront.
     *
     * @param string $languageCodeHL The language code in HL format.
     * @param string $bookId The book ID (e.g., "LUK" for Luke).
     * @param int $chapterStart The starting chapter number.
     * @param int|null $verseStart The starting verse number.
     * @param int|null $verseEnd The ending verse number.
     * @return void
     */
    public function fetchPassageJson($languageCodeHL, $bookId, $chapterStart, $verseStart = null, $verseEnd = null)
    {
        $response = $this->passageService->getExternalPassage($languageCodeHL, $bookId, $chapterStart, $verseStart, $verseEnd);

        if (isset($response->data[0]->path)) {
            $this->getPassageJson($response->data[0]->path);
        } else {
            $this->json = null; // Handle the case where no data is returned
        }
    }

    /**
     * Retrieves JSON passage data from a CloudFront URL.
     *
     * @param string $url The URL to fetch JSON data from.
     * @return void
     */
    private function getPassageJson($url)
    {
        $jsonData = new CloudFrontConnectionService($url);
        $this->json = $jsonData->response;
    }

    /**
     * Get the fetched JSON data.
     *
     * @return mixed The JSON response data.
     */
    public function getJson()
    {
        return $this->json;
    }
}
