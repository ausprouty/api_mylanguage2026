
<?php

declare(strict_types=1);

namespace App\Services\BiblePassage;

use App\Configuration\Config;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;

final class DbtPassageService implements BiblePassageProviderInterface
{
    /**
     * Return a Bible.is link for the requested passage.
     *
     * This is a link-only provider.
     * It does not download passage text.
     *
     * BPID generation and database operations are handled
     * by BiblePassageService.
     */
    public function fetchPassage(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): PassageModel {

        $passage = new PassageModel();

        // Public Bible.is URL.
        $passage->setPassageUrl(
            $this->buildPassageUrl($bible, $reference)
        );

        // Reference to display when no local-language
        // book name is available.
        $passage->setReferenceLocalLanguage(
            $reference->getEntry()
        );

        // Intentionally no Bible text.
        $passage->setPassageText('');

        return $passage;
    }

    /**
     * Build the public Bible.is chapter URL.
     *
     * Example:
     * https://live.bible.is/bible/MYABJB/LUK/1
     */
    private function buildPassageUrl(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): string {

        $version = trim((string) $bible->getExternalId());

        $book = trim((string) $reference->getBookID());

        $chapter = (int) $reference->getChapterStart();

        if ($version === '' || $book === '' || $chapter <= 0) {
            throw new \InvalidArgumentException(
                'Missing Bible.is version, book, or chapter.'
            );
        }

        $baseUrl = rtrim(
            (string) Config::get(
                'endpoints.dbt',
                'https://live.bible.is'
            ),
            '/'
        );

        return sprintf(
            '%s/bible/%s/%s/%d',
            $baseUrl,
            rawurlencode($version),
            rawurlencode($book),
            $chapter
        );
    }
}
