<?php

declare(strict_types=1);

namespace App\Controllers\BiblePassage;

use App\Factories\PassageReferenceFactory;
use App\Models\Bible\BibleModel;
use App\Repositories\BibleRepository;
use App\Services\BiblePassage\BiblePassageService;
use App\Services\LoggerService;

class PassageRetrieverController
{
    public function __construct(
        private BibleRepository $bibleRepository,
        private PassageReferenceFactory $referenceFactory,
        private BiblePassageService $passageService
    ) {
    }

    /**
     * Retrieve a Bible passage from an HTTP request.
     *
     * Accepts:
     *   bid                Bible database ID
     *   languageCodeHL     Alternatively, select Bible by HL language
     *   languageCodeGoogle Alternatively, select Bible by Google language
     *   entry              Bible reference (e.g. John 4:1-7)
     *
     * Returns:
     *   bid, entry, text, url, ref, error
     */
    public function __invoke(array $args): array
    {
        $body = $args['body'] ?? [];

        if (!is_array($body)) {
            $body = [];
        }

        // Request parameters
        $bid = (int) ($body['bid'] ?? 0);

        $entry = trim((string) ($body['entry'] ?? ''));

        $hl = trim((string) (
            $body['languageCodeHL'] ?? $body['hl'] ?? ''
        ));

        $google = trim((string) (
            $body['languageCodeGoogle'] ?? $body['google'] ?? ''
        ));

        // Default response
        $response = [
            'bid'   => $bid,
            'entry' => $entry,
            'text'  => '',
            'url'   => '',
            'ref'   => '',
            'error' => '',
        ];

        // Validate request
        if ($entry === '') {
            $response['error'] = 'Bible reference is required.';
            return $response;
        }

        if ($bid <= 0 && $hl === '' && $google === '') {
            $response['error'] =
                'Provide bid, languageCodeHL, or languageCodeGoogle.';
            return $response;
        }

        try {
            // 1. Find the Bible
            $bible = $this->findBible($bid, $hl, $google);

            if ($bible === null) {
                $response['error'] = 'No matching Bible found.';
                return $response;
            }

            $response['bid'] = (int) $bible->getBid();

            // 2. Create the passage reference
            $reference = $this->referenceFactory->createFromEntry(
                $entry,
                (string) $bible->getLanguageCodeHL()
            );

            // 3. Retrieve the complete PassageModel
            $passage = $this->passageService->getPassageModel(
                $bible,
                $reference
            );

            // 4. Build response
            $response['text'] = (string) $passage->getPassageText();

            $response['url'] = (string) $passage->getPassageUrl();

            $response['ref'] = (string)
                $passage->getReferenceLocalLanguage();

        } catch (\Throwable $e) {

            LoggerService::logError(
                'PassageRetrieverController.exception',
                [
                    'bid'     => $bid,
                    'entry'   => $entry,
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]
            );

            $response['error'] =
                'Unable to retrieve Bible passage.';
        }

        return $response;
    }

    /**
     * Find the Bible using the available identifier.
     *
     * Priority:
     *   1. Bible ID
     *   2. HL language code
     *   3. Google language code
     */
    private function findBible(
        int $bid,
        string $hl,
        string $google
    ): ?BibleModel {

        if ($bid > 0) {
            return $this->bibleRepository->findBibleByBid($bid);
        }

        if ($hl !== '') {
            return $this->bibleRepository
                ->findBestBibleByLanguageCodeHL($hl);
        }

        return $this->bibleRepository
            ->findBestBibleByLanguageCodeGoogle($google);
    }
}
