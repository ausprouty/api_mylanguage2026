<?php

declare(strict_types=1);

namespace App\Services\BiblePassage;

use App\Configuration\Config;
use App\Factories\BibleWordConnectionFactory;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Services\LoggerService;

final class BibleWordPassageService implements BiblePassageProviderInterface
{
    public function __construct(
        private BibleWordConnectionFactory $connectionFactory
    ) {
    }

    /**
     * Retrieve a WordProject passage.
     *
     * Returns a populated PassageModel.
     * No database operations or BPID calculation.
     */
    public function fetchPassage(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): PassageModel {

        $passage = new PassageModel();

        // Public Read More URL
        $passage->setPassageUrl(
            $this->buildPassageUrl($bible, $reference)
        );

        // Retrieve chapter HTML
        $html = $this->getChapterHtml($bible, $reference);

        if ($html === '') {
            return $passage;
        }

        // Extract requested verses
        $chapter = $this->trimToChapter($html);
        $text = $this->trimToVerses($chapter, $reference);

        if ($text === '') {
            LoggerService::logError(
                'BibleWordPassageService',
                'No verses found: ' . $reference->getEntry()
            );

            return $passage;
        }

        $passage->setPassageText($text);

        // Local-language reference
        $passage->setReferenceLocalLanguage(
            $this->buildLocalReference($html, $reference)
        );

        return $passage;
    }

    /**
     * WordProject base URL.
     */
    private function baseUrl(): string
    {
        return rtrim(
            (string) Config::get(
                'endpoints.wordproject',
                'https://wordproject.org/bibles'
            ),
            '/'
        );
    }

    /**
     * Public chapter URL.
     */
    private function buildPassageUrl(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): string {

        return $this->baseUrl()
            . '/' . $bible->getExternalId()
            . '/' . $this->formatChapterPage($reference)
            . '.htm';
    }

    /**
     * Retrieve chapter HTML.
     * Prefer locally stored HTML where available.
     */
    private function getChapterHtml(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): string {

        $local = $this->generateFilePath($bible, $reference);

        if (is_file($local)) {
            LoggerService::logDebug(
                'BibleWordPassageService.cache',
                'Local file: ' . $local
            );

            $html = file_get_contents($local);

            if ($html === false) {
                throw new \RuntimeException(
                    'Unable to read WordProject file: ' . $local
                );
            }

            return $html;
        }

        // No local file: fetch from WordProject
        $endpoint = $bible->getExternalId()
            . '/' . $this->formatChapterPage($reference)
            . '.htm';

        $conn = $this->connectionFactory->fromPath(
            $endpoint,
            salvageJson: false
        );

        $html = $conn->getBody();

        if ($html === '') {
            throw new \RuntimeException(
                'Empty WordProject response: ' . $endpoint
            );
        }

        return $html;
    }

    /**
     * Local HTML file location.
     */
    private function generateFilePath(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): string {

        $root = rtrim(
            Config::getDir('resources.root'),
            "/\\"
        );

        $relativePath = 'bibles/wordproject/'
            . $bible->getExternalId()
            . '/'
            . $this->formatChapterPage($reference)
            . '.html';

        return $root . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath
            );
    }

    /**
     * WordProject chapter path, e.g. "42/7".
     */
    private function formatChapterPage(
        PassageReferenceModel $reference
    ): string {

        $book = str_pad(
            (string) (int) $reference->getBookNumber(),
            2,
            '0',
            STR_PAD_LEFT
        );

        return $book . '/' . $reference->getChapterStart();
    }

    /**
     * Remove content outside the chapter.
     */
    private function trimToChapter(string $html): string
    {
        $startMarker = '<!--... the Word of God:-->';
        $endMarker = '<!--... sharper than any twoedged sword... -->';

        $start = strpos($html, $startMarker);
        $end = strpos($html, $endMarker);

        if (
            $start === false ||
            $end === false ||
            $end <= $start
        ) {
            return $html;
        }

        $start += strlen($startMarker);

        return substr($html, $start, $end - $start);
    }

    /**
     * Extract and wrap the requested verses.
     */
    private function trimToVerses(
        string $html,
        PassageReferenceModel $reference
    ): string {

        $selected = $this->selectVerses($html, $reference);

        if ($selected === '') {
            return '';
        }

        return "\n<!-- begin bible -->"
            . $selected
            . "\n<!-- end bible -->\n";
    }

    /**
     * Select verses from WordProject HTML.
     */
    private function selectVerses(
        string $html,
        PassageReferenceModel $reference
    ): string {

        $html = str_replace(
            [
                '<!--span class="verse"',
                '</span-->',
                '<p>',
                '</p>',
                '<br/>',
                '<br />'
            ],
            [
                '<span class="verse"',
                '</span>',
                '',
                '',
                '<br>',
                '<br>'
            ],
            $html
        );

        $html = preg_replace(
            '/<span class="dimver(?:se)?">.*?<\/span>/us',
            '',
            $html
        );

        $html = preg_replace(
            '/<sup>\d+<\/sup>/u',
            '',
            $html
        );

        $start = (int) $reference->getVerseStart();
        $end = (int) $reference->getVerseEnd();

        $output = '';

        foreach (explode('<br>', $html) as $line) {

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $number = $this->extractVerseNumber($line);

            if (
                $number >= $start &&
                $number <= $end
            ) {
                $output .= $this->formatVerseLine(
                    $number,
                    $line
                );
            }
        }

        return $output;
    }

    /**
     * Format a single verse.
     */
    private function formatVerseLine(
        int $verseNumber,
        string $line
    ): string {

        $line = preg_replace(
            '/^\s*<sup>\d+<\/sup>\s*/u',
            '',
            $line
        );

        $line = preg_replace(
            '/^\s*<span class="dimver(?:se)?">.*?<\/span>\s*/u',
            '',
            $line
        );

        $verseText = preg_replace(
            '/^.*?<span class="verse"[^>]*id="\d+"[^>]*>\s*\d+\s*<\/span>\s*/us',
            '',
            $line
        );

        if ($verseText === null) {
            $verseText = $line;
        }

        return '<p><sup>'
            . $verseNumber
            . '</sup>'
            . $verseText
            . "</p>\n";
    }

    /**
     * Extract verse number from HTML.
     */
    private function extractVerseNumber(string $line): int
    {
        if (preg_match(
            '/<span class="verse"[^>]*id="\d+"[^>]*>\s*(\d+)\s*<\/span>/u',
            $line,
            $matches
        )) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Construct the local-language reference
     * from the HTML page title.
     */
    private function buildLocalReference(
        string $html,
        PassageReferenceModel $reference
    ): string {

        $vs = (int) $reference->getVerseStart();
        $ve = (int) $reference->getVerseEnd();

        $verses = $vs === $ve
            ? (string) $vs
            : $vs . '-' . $ve;

        $chapter = (int) $reference->getChapterStart();

        // Fallback when no title exists
        if (
            !preg_match(
                '/<title>(.*?)<\/title>/isu',
                $html,
                $matches
            )
        ) {
            return $chapter . ':' . $verses;
        }

        $title = trim(
            html_entity_decode(
                strip_tags($matches[1]),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );

        if ($title === '') {
            return $chapter . ':' . $verses;
        }

        // Take text after last comma
        if (preg_match('/[,，]/u', $title)) {
            $parts = preg_split('/[,，]/u', $title);
            $title = trim(end($parts));
        }

        // Remove publisher suffix
        $title = preg_replace(
            '/\s*[;；].*$/u',
            '',
            $title
        );

        // Convert "3GJONI" to "3 GJONI"
        $title = preg_replace(
            '/^(\d)(\p{L})/u',
            '$1 $2',
            trim($title)
        );

        // Korean-style: "예레미야 애가 1: ..."
        if (preg_match(
            '/^(.+?)\s+(\d+)\s*[:：]/u',
            $title,
            $m
        )) {
            return trim($m[1])
                . ' ' . $m[2] . ':' . $verses;
        }

        // "Song of Songs chapter 1"
        if (preg_match(
            '/^(.+?)\s+(?:cap[ií]tulo|chapter)\s+(\d+)\s*$/iu',
            $title,
            $m
        )) {
            return trim($m[1])
                . ' ' . $m[2] . ':' . $verses;
        }

        // Generic: "GJONI 3"
        if (preg_match(
            '/^(.+?)\s+(\d+)\s*$/u',
            $title,
            $m
        )) {
            return trim($m[1])
                . ' ' . $m[2] . ':' . $verses;
        }

        return $title . ':' . $verses;
    }
}
