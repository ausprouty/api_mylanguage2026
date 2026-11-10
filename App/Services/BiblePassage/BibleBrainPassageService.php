<?php

declare(strict_types=1);

namespace App\Services\BiblePassage;

use App\Factories\BibleBrainConnectionFactory;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Services\LoggerService;

final class BibleBrainPassageService implements BiblePassageProviderInterface
{
    public function __construct(
        private BibleBrainConnectionFactory $connectionFactory
    ) {
    }

    /**
     * Fetch a TextPlain passage from BibleBrain.
     *
     * No caching, database saving, BPID generation,
     * or numeral localisation.
     */
    public function fetchPassage(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): PassageModel {

        $passage = new PassageModel();

        // Public Read More URL.
        $passage->setPassageUrl(
            $this->buildPassageUrl($bible, $reference)
        );

        // BibleBrain TextPlain endpoint.
        $endpoint = sprintf(
            '/api/bibles/filesets/%s/%s/%s',
            rawurlencode((string) $bible->getExternalId()),
            rawurlencode((string) $reference->getBookID()),
            $reference->getChapterStart()
        );

        $query = [
            'verse_start' => (string) $reference->getVerseStart(),
            'verse_end'   => (string) $reference->getVerseEnd(),
        ];

        LoggerService::logDebug(
            'BibleBrainPassageService.request',
            [
                'endpoint' => $endpoint,
                'query'    => $query,
            ]
        );

        // The connection factory handles the API URL and key.
        $conn = $this->connectionFactory->fromPath(
            $endpoint,
            $query
        );

        $json = $conn->getJson();

        // Accept either array or object JSON responses.
        $data = is_object($json)
            ? ($json->data ?? [])
            : (is_array($json) ? ($json['data'] ?? []) : []);

        if (!is_array($data) || $data === []) {
            LoggerService::logError(
                'BibleBrainPassageService',
                'Empty response: ' . $endpoint
            );

            return $passage;
        }

        // Format the selected verses.
        $text = $this->formatVerses($data);

        if ($text === '') {
            LoggerService::logError(
                'BibleBrainPassageService',
                'No passage text: ' . $endpoint
            );

            return $passage;
        }

        $passage->setPassageText($text);

        // Local-language book name and reference.
        $passage->setReferenceLocalLanguage(
            $this->buildLocalReference($data, $reference)
        );

        return $passage;
    }

    /**
     * Build the public Bible.is Read More URL.
     */
    private function buildPassageUrl(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): string {

        return sprintf(
            'https://live.bible.is/bible/%s/%s/%s',
            rawurlencode((string) $bible->getExternalId()),
            rawurlencode((string) $reference->getUversionBookID()),
            $reference->getChapterStart()
        );
    }

    /**
     * Convert BibleBrain verses into HTML.
     *
     * Accepts verse records as arrays or objects.
     */
    private function formatVerses(array $data): string
    {
        $html = '';

        foreach ($data as $item) {

            $verse = (array) $item;

            $start = $verse['verse_start'] ?? null;
            $end   = $verse['verse_end'] ?? null;
            $text  = trim((string) ($verse['verse_text'] ?? ''));

            if ($start === null || $text === '') {
                continue;
            }

            $number = (string) $start;

            if ($end !== null && (string) $end !== $number) {
                $number .= '-' . $end;
            }

            $html .= sprintf(
                '<p><sup class="versenum">%s</sup> %s</p>',
                htmlspecialchars(
                    $number,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ),
                htmlspecialchars(
                    $text,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                )
            );
        }

        return $html;
    }

    /**
     * Construct the local-language Bible reference.
     *
     * Uses BibleBrain's book_name_alt when available.
     */
    private function buildLocalReference(
        array $data,
        PassageReferenceModel $reference
    ): string {

        $first = (array) ($data[0] ?? []);

        $book = trim((string) (
            $first['book_name_alt']
            ?? $first['book_name']
            ?? ''
        ));

        $chapter = $reference->getChapterStart();
        $start   = $reference->getVerseStart();
        $end     = $reference->getVerseEnd();

        $verseRange = (string) $start;

        if ($start !== $end) {
            $verseRange .= '-' . $end;
        }

        $numbers = "{$chapter}:{$verseRange}";

        return trim($book . ' ' . $numbers);
    }
}
