<?php

declare(strict_types=1);

namespace App\Controllers\BiblePassage;

use App\Repositories\BibleRepository;
use App\Factories\PassageReferenceFactory;
use App\Services\BiblePassage\BiblePassageService;
use App\Services\LoggerService;

/**
 * HTTP controller for retrieving a Bible passage.
 *  written Dec 2025 by ChatGPT
 *
 * Expects a body with:
 *  - bid   : integer Bible ID
 *  - entry : string reference ("John 4:1-7")
 *
 * Always returns a payload with:
 *  - bid
 *  - entry
 *  - text  (may be "")
 *  - url   (may be "")
 *  - ref   (may be "")
 *  - error ("" on success, message on error)
 */
class PassageRetrieverController
{
    public function __construct(
        private BibleRepository $bibleRepository,
        private PassageReferenceFactory $referenceFactory,
        private BiblePassageService $passageService
    ) {
    }

    public function __invoke(array $args): array
    {
        $body = $args['body'] ?? [];
        if (!is_array($body)) {
            $body = [];
        }

        $bid   = (int) ($body['bid'] ?? 0);
        $entry = (string) ($body['entry'] ?? '');

        // Base response shape: always include bid + entry
        $response = [
            'bid'   => $bid,
            'entry' => $entry,
            'text'  => '',
            'url'   => '',
            'ref'   => '',
            'error' => '',
        ];

        // Log raw inputs for your server-side debugging
        LoggerService::logInfo(
            'PassageRetrieverController.input',
            ['bid' => $bid, 'entry' => $entry]
        );

        // If either bid or entry is missing/invalid, return a soft error.
        if ($bid <= 0 || $entry === '') {
            $response['error'] =
                'Both "bid" (Bible ID) and "entry" (reference) are required.';
            return $response;
        }

        try {
            // 1) Load Bible metadata
            $bible = $this->bibleRepository->findBibleByBid($bid);
            if ($bible === null) {
                $response['error'] =
                    "No Bible found for bid {$bid}.";
                LoggerService::logError(
                    'PassageRetrieverController.noBible',
                    ['bid' => $bid]
                );
                return $response;
            }

            $languageCodeHL = (string) $bible->getLanguageCodeHL();

            // 2) Build a PassageReferenceModel from the entry + language
            $reference = $this->referenceFactory
                ->createFromEntry($entry, $languageCodeHL);

            // 3) Use the existing BiblePassageService
            $passageModel = $this->passageService
                ->getPassageModel($bible, $reference);

           // 4) Fill success data
            $response['text'] = (string) $passageModel->getPassageText();
            $response['url']  = (string) $passageModel->getPassageUrl();
            $response['ref']  =
                (string) $passageModel->getReferenceLocalLanguage();
            // error stays ""
 
            return $response;
        } catch (\Throwable $e) {
            // Log full details for you
            LoggerService::logError(
                'PassageRetrieverController.exception',
                [
                    'bid'     => $bid,
                    'entry'   => $entry,
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]
            );

            // Return a message that the *remote developer* will see
            $response['error'] =
                'Error while retrieving passage: ' .
                $e->getMessage();

            return $response;
        }
    }
}
