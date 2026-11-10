<?php

declare(strict_types=1);

namespace App\Services\BiblePassage;

use App\Factories\YouVersionConnectionFactory;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Services\LoggerService;
use App\Services\Web\YouVersionConnectionService;

final class YouVersionPassageService implements BiblePassageProviderInterface
{
    public function __construct(
        private YouVersionConnectionFactory $connectionFactory
    ) {
    }

    /**
     * Retrieve a passage from YouVersion.
     *
     * Returns a populated PassageModel.
     *
     * No BPID generation, database operations,
     * caching, or numeral localisation.
     */
    public function fetchPassage(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): PassageModel {

        $passage = new PassageModel();

        // Build YouVersion endpoint.
        $endpoint = $this->buildEndpointPath(
            $bible,
            $reference
        );

        // Public Read More URL.
        $passage->setPassageUrl(
            $this->buildPassageUrl($endpoint)
        );

        // Retrieve HTML from YouVersion.
        $conn = $this->connectionFactory->fromPath(
            $endpoint,
            autoFetch: true,
            salvageJson: false
        );

        $html = $conn->getBody();

        if ($html === '') {
            LoggerService::logError(
                'YouVersionPassageService',
                'Empty response: ' . $endpoint
            );

            return $passage;
        }

        // Extract passage text and reference.
        $extracted = YouVersionJsonExtractorService
            ::extractFirstVerse($html);

        $text = (string) ($extracted['content'] ?? '');

        $localReference = (string) (
            $extracted['reference_human'] ?? ''
        );

        if (trim($text) === '') {
            LoggerService::logError(
                'YouVersionPassageService',
                'No passage text: ' . $endpoint
            );

            return $passage;
        }

        $passage->setPassageText($text);

        $passage->setReferenceLocalLanguage(
            $localReference
        );

        return $passage;
    }

    /**
     * Build the public YouVersion Read More URL.
     */
    private function buildPassageUrl(string $endpoint): string
    {
        $baseUrl = rtrim(
            YouVersionConnectionService::getBaseUrl(),
            '/'
        );

        return $baseUrl . '/' . ltrim($endpoint, '/');
    }

    /**
     * Build the relative YouVersion endpoint.
     *
     * Example externalId:
     *   111/JHN.%.NIV
     *
     * Example completed endpoint:
     *   111/JHN.JHN.3.16-18.NIV
     *
     * The substitution deliberately preserves the
     * format used in the existing application.
     */
    private function buildEndpointPath(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): string {

        $bookId = $reference->getUversionBookID();

        $chapter = $reference->getChapterStart();

        $verseStart = $reference->getVerseStart();
        $verseEnd = $reference->getVerseEnd();

        $replacement = sprintf(
            '%s.%s.%s-%s',
            $bookId,
            $chapter,
            $verseStart,
            $verseEnd
        );

        // Replace the placeholder in externalId.
        $endpoint = str_replace(
            '%',
            $replacement,
            $bible->getExternalId()
        );

        // Encode the translation identifier
        // after the final dot.
        $lastDot = strrpos($endpoint, '.');

        if ($lastDot !== false) {
            $before = substr($endpoint, 0, $lastDot + 1);
            $after = substr($endpoint, $lastDot + 1);

            $endpoint = $before . rawurlencode($after);
        }

        // Handle an externalId stored as a full URL.
        if (preg_match(
            '#https?://[^/]+/bible/(.+)$#i',
            $endpoint,
            $matches
        )) {
            return $matches[1];
        }

        return ltrim($endpoint, '/');
    }
}
