<?php

namespace App\Controllers;

use App\Services\Languages\LanguagesAvailableService;

/**
 * LanguagesAvailableController
 *
 * POST /api/v2/bible/languages
 *
 * Purpose:
 *   Return a list of languages and which products are available
 *   in each language (Bible, Jesus film, tracts, Spirit presentation,
 *   Q&A website, Google machine translation).
 *
 * Request (JSON body):
 *   {
 *     "products": ["bible", "jesus_video", "gospel_tract",
 *                  "holy_spirit_presentation", "qna_website", "google_mt"],
 *     "onlyWithBible": true,
 *     "biblePortion" : ["NT","OT", "C" (complete)],
 *     "bibleFormat" : ["text", "link"],
 *     "languages"   : ["all", "google_mt", or a list of languageCodeIso]
 *     "includeMeta": true
 *   }
 *
 * All fields are optional. If "products" is omitted, a sensible default
 * will be used (e.g. ["bible", "google_mt"]).
 *
 * Response (JSON):
 *   {
 *     "languages": [
 *       {
 *         "languageCodeIso": "eng",
 *         "languageCodeHL": "en",
 *         "name": "English",
 *         "products": {
 *           "bible": {
 *              "available": true,          // overall: do we have *any* Bible?
 *              "nt": {
 *                  "available": true,
 *                  "format": "text",         // text | link | none
 *                  "bid": "1257"             // your internal Bible id for NT
 *              },
 *              "ot": {
 *                  "available": false,
 *                  "format": "none",
 *                  "bid": null               // or omit if not available
 *              },
 *              "complete": false           // convenience flag: true if both nt+ot available
 *           }
 *           "jesus_video": {
 *             "available": true,
 *             "url": "https://..."
 *           },
 *           "gospel_tract": {
 *             "available": false
 *           },
 *           "holy_spirit_presentation": {
 *             "available": true,
 *             "url": "https://..."
 *           },
 *           "qna_website": {
 *             "available": true,
 *             "url": "https://..."
 *           },
 *           "google_mt": {
 *             "available": true
 *           }
 *         }
 *       }
 *     ],
 *     "meta": {
 *       "total": 123,
 *       "productsRequested": ["bible", "google_mt"]
 *     }
 *   }
 */
class LanguagesAvailableController
{
    /** @var LanguagesAvailableService */
    private $service;

    public function __construct(LanguagesAvailableService $service)
    {
        $this->service = $service;
    }

    /**
     * Handle the POST request.
     *
     * @param array<string,mixed> $args Route parameters (not used currently).
     * @return string JSON-encoded response.
     */
    public function __invoke(array $args = []): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            $payload = [];
        }

        $languages = $this->service->getLanguagesWithProducts($payload);

        return json_encode($languages);
    }
}
