<?php

declare(strict_types=1);

namespace App\Controllers\BiblePassage;

use App\Repositories\BibleRepository;
use App\Factories\PassageReferenceFactory;
use App\Services\BiblePassage\BiblePassageService;
use App\Configuration\Config;
use App\Services\LoggerService;

/**
 * HTTP controller for retrieving a Bible passage.
 *
 * This is a thin wrapper:
 *  - Reads input (bid + entry) from the request args.
 *  - Uses domain services to resolve the Bible and reference.
 *  - Delegates to PassageService to load / fetch the passage.
 *  - Returns a simple array for the HTTP layer to serialise.
 */
class PassageRetrieverController
{
    /**
     * @param BibleRepository          $bibleRepository
     *        Used to load the Bible row for the given bid.
     *
     * @param PassageReferenceFactory  $referenceFactory
     *        Builds a PassageReferenceModel from the entry
     *        string (e.g. "John 4:1-7") and the Bible.
     *
     * @param BiblePassageService           $passageService
     *        Core application logic: checks cache, fetches
     *        external text if needed, applies localisation,
     *        and returns a PassageModel.
     */
    public function __construct(
        private BibleRepository $bibleRepository,
        private PassageReferenceFactory $referenceFactory,
        private BiblePassageService $passageService
    ) {
    }

    /**
     * Route entry point.
     *
     * Expected input (JSON body or similar):
     *  - bid   : integer Bible ID.
     *  - entry : string reference ("John 4:1-7").
     *
     * @param array $args
     *        Router-provided arguments. The exact shape
     *        depends on the front controller, but we expect
     *        $args['body'] to contain the parsed request body.
     *
     * @return array
     *         Simple associative array with:
     *         - text : the passage text (if available).
     *         - url  : external URL (if any).
     *         - ref  : localised reference label.
     */
    public function __invoke(array $args): array
    {
        // Pull the parsed body out of the router args.
        // If the router does not provide a 'body' key,
        // we treat it as an empty array.
        // 1) Try to get body from router args
        $body = $args['body'] ?? null;

        // 2) If not provided, try to read JSON from php://input
        if (!$body || !is_array($body)) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $body = $decoded;
            } else {
                // Fallback to form-encoded POST (just in case)
                $body = $_POST ?? [];
            }
        }

        // Bible ID (numeric) and reference string.
        $bid   = (int) ($body['bid'] ?? 0);
            LoggerService::logInfo(
                'Passage Retriever Controller',
                ['bid' => $bid]
            );
        $entry = (string) ($body['entry'] ?? '');
        LoggerService::logInfo(
                'Passage Retriever Controller',
                ['entry' => $entry]
            );

        // 1) Load the Bible metadata for this bid.
        $bible = $this->bibleRepository->findBibleByBid($bid);
                LoggerService::logInfo(
                'Passage Retriever Controller',
                ['bible' => json_encode($bible)]
            );

        $languageCodeHL = $bible->getLanguageCodeHL();

        // 2) Build a PassageReferenceModel from the entry
        //    string and the Bible (book, chapter, verses).
        $reference = $this->referenceFactory
            ->createFromEntry($entry, $languageCodeHL);

        // 3) Delegate to the PassageService. It will:
        //    - check the DB cache,
        //    - fetch from external source if needed,
        //    - apply localisation,
        //    - return a PassageModel.
        // Use your existing, mature passage system
        $passageModel = $this->passageService
            ->getPassageModel($bible, $reference);

        // 4) Return a simple array for the HTTP layer
        //    to encode as JSON or similar.
        return [
            'text' => $passageModel->getPassageText(),
            'url'  => $passageModel->getPassageUrl(),
            'ref'  => $passageModel->getReferenceLocalLanguage(),
        ];
    }
}
