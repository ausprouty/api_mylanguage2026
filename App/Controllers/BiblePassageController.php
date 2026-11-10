<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Factories\PassageReferenceFactory;
use App\Models\Bible\BibleModel;
use App\Repositories\BibleRepository;
use App\Services\BiblePassage\BiblePassageService;
use App\Services\LoggerService;

final class BiblePassageController
{
    public function __construct(
        private BibleRepository $bibleRepository,
        private PassageReferenceFactory $referenceFactory,
        private BiblePassageService $passageService
    ) {
    }

    /**
     * Retrieve a Bible passage.
     *
     * Request body:
     *   entry              Required Bible reference
     *   bid                Optional Bible ID
     *   languageCodeHL     Optional HL language code
     *   languageCodeGoogle Optional Google language code
     *
     * Bible selection priority:
     *   1. bid
     *   2. languageCodeHL
     *   3. languageCodeGoogle
     *
     * Response:
     *   bid, entry, text, url, ref, error
     */
    public function __invoke(array $args): array
    {
        $body = $args['body'] ?? [];

        if (!is_array($body)) {
            $body = [];
        }

        $bid = (int) ($body['bid'] ?? 0);

        $entry = trim((string) ($body['entry'] ?? ''));

        $hl = trim((string) (
            $body['languageCodeHL'] ?? $body['hl'] ?? ''
        ));

        $google = trim((string) (
            $body['languageCodeGoogle'] ?? $body['google'] ?? ''
        ));

        // Maintain the existing API response format.
        $response = [
            'bid'   => $bid,
            'entry' => $entry,
            'text'  => '',
            'url'   => '',
            'ref'   => '',
            'error' => '',
        ];

        // Validate request.
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
            // 1. Get a populated BibleModel.
            $bible = $this->findBible($bid, $hl, $google);

            if ($bible === null) {
                $response['error'] = 'No matching Bible found.';
                return $response;
            }

            $response['bid'] = (int) $bible->getBid();

            // 2. Get a populated PassageReferenceModel.
            $reference = $this->referenceFactory->createFromEntry(
                $entry,
                (string) $bible->getLanguageCodeHL()
            );

            // 3. Retrieve the complete PassageModel.
            $passage = $this->passageService->getPassage(
                $bible,
                $reference
            );

            // 4. Prepare the API response.
            $response['text'] = $passage->getPassageText();

            $response['url'] = $passage->getPassageUrl();

            $response['ref'] =
                $passage->getReferenceLocalLanguage();

        } catch (\Throwable $e) {
            LoggerService::logError(
                'BiblePassageController.exception',
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
     * Find the requested Bible.
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
