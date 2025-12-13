<?php

namespace App\Controllers\Language;

use App\Services\Languages\LanguagesAvailableService;
use App\Services\LoggerService;


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



/**
     * Handle the POST request.
     *
     * @param array<string,mixed> $args
     *        Router arguments. We expect $args['body'] to contain
     *        the parsed and sanitised request payload.
     *
     * @return array{
     *   error: string,
     *   languages: array<mixed>,
     *   meta: array<mixed>|null
     * }
     */
class LanguagesAvailableController
{
    /** @var LanguagesAvailableService */


    public function __construct(
        private LanguagesAvailableService $service)
    {  
    }

    
    public function __invoke(array $args = []): array
    {
        $body = $args['body'] ?? [];
        if (!is_array($body)) {
            $body = [];
        }
        try {
            // Delegate all business logic to the service.
            $result = $this->service->getLanguagesWithProducts($body);

            // If the service already returns the final shape, pass it through.
            // Otherwise, normalise here.
            return [
                'error'     => '',
                'languages' => $result['languages'] ?? $result,
                'meta'      => $result['meta']      ?? null,
            ];
        } catch (\Throwable $e) {
            // Log full details for you
            LoggerService::logError(
                'LanguagesAvailableController.exception',
                [
                    'payload' => $body,
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]
            );

            // Return a clear error message for the remote developer
            return [
                'error'     => 'Error while retrieving languages: ' . $e->getMessage(),
                'languages' => [],
                'meta'      => null,
            ];
        }
    }
}
