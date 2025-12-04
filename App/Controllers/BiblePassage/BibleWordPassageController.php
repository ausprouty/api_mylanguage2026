<?php

namespace App\Controllers\BiblePassage;

use App\Configuration\Config;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Services\LoggerService;
use App\Services\Web\BibleWordConnectionService;

class BibleWordPassageController
{
    private BibleModel $bible;
    private PassageReferenceModel $bibleReference;

    /**
     * @param PassageReferenceModel $bibleReference
     * @param BibleModel            $bible
     */

    public function __construct(
        PassageReferenceModel $bibleReference,
        BibleModel $bible
    ) {
        $this->bible          = $bible;
        $this->bibleReference = $bibleReference;
    }
    /**
     * Fetch a passage from BibleWord / WordProject.
     *
     * 1) Try local server file
     * 2) If not available, fetch from the web
     * 3) Clean / extract text + reference
     * 4) Return a hydrated PassageModel
     */
    public function fetchPassage(): PassageModel
    {
        $passage = new PassageModel();
        $passage->setBpid($this->bibleReference->getBpid());

        // 1) Try local server file
        $body = $this->fetchFromServerFile();

        // 2) Fall back to web if needed
        if ($body === null || $body === '') {
            $body = $this->fetchFromWeb();
        }

        if ($body === null || $body === '') {
            LoggerService::logError(
                'BibleWordPassageController-140',
                'Failed to fetch Bible passage from WordProject.'
            );
            return $passage;
        }

        // 3) Clean / extract verses
        $text = $this->trimToVerses($body);
        if ($text === null || $text === '') {
            LoggerService::logError(
                'BibleWordPassageController-145',
                'Unable to extract Bible Word Text.'
            );
            return $passage;
        }

        $passage->setPassageText($text);

        // 4) Extract reference in local language
        $passage->setReferenceLocalLanguage(
            $this->extractReferenceLanguage($body)
        );

        // Optional: record a logical "URL" / key we used

        $baseUrl = rtrim(
            (string) Config::get(
                'endpoints.wordproject',
                'https://wordproject.org/bibles'
            ),
            '/'
        );
        $endpoint = $baseUrl
            . '/' . $this->bible->getExternalId()
            . '/' . $this->formatChapterPage() . '.htm';
        $passage->setPassageUrl($endpoint);

        return $passage;
    }

    /**
     * Try loading the HTML from a local cached server file.
     *
     * @return string|null
     */
    private function fetchFromServerFile(): ?string
    {
        $filePath = $this->generateFilePath();
        return $this->loadLocalContent($filePath);
    }

    /**
     * Fetch the HTML from WordProject via HTTP.
     *
     * @return string|null
     */
    private function fetchFromWeb(): ?string
    {
        $endpoint = $this->bible->getExternalId()
            . '/' . $this->formatChapterPage() . '.htm';

        $webpage = new BibleWordConnectionService($endpoint);

        if (empty($webpage->response)) {
            LoggerService::logError(
                'BibleWordPassageController-74',
                'Failed to fetch Bible passage from WordProject.'
            );
            return null;
        }

        return $webpage->response;
    }

   
    

    /**
     * Formats the chapter and page structure for the URL or file path.
     *
     * @return string The formatted chapter and page.
     */
    private function formatChapterPage()
    {
        $bookNumber = $this->bibleReference->getBookNumber();
        if (strlen($bookNumber) === 1) {
            $bookNumber = str_pad($bookNumber, 2, '0', STR_PAD_LEFT);
        }
        $chapterNumber = $this->bibleReference->getChapterStart();
        return $bookNumber . '/' . $chapterNumber;
    }

    /**
     * Formats and cleans external text from the webpage.
     *
     * @param string $webpage The raw HTML content.
     * @return string The formatted passage text.
     */
    private function trimToVerses($webpage)
    {
        $chapter = $this->trimToChapter($webpage);
        $selectedVerses = $this->selectVerses($chapter);

        return "\n<!-- begin bible -->" . $selectedVerses .
            "\n<!-- end bible -->\n";
    }

    /**
     * Generates the file path for a local resource.
     *
     * @return string The generated file path.
     */
    private function generateFilePath()
    {
        $baseDir = Config::getDir('resources.root') . 'bibles/wordproject/';
        $externalId = $this->bible->getExternalId();
        return $baseDir . $externalId . '/' . $externalId . '/'
            . $this->formatChapterPage();
    }

    /**
     * Loads webpage content from a file.
     *
     * @param string $filePath The path to the file.
     * @return string|null The file content or null if not found.
     */
    private function loadLocalContent($filePath)
    {
        if (file_exists($filePath . '.html')) {
            return file_get_contents($filePath . '.html');
        } elseif (file_exists($filePath . '.htm')) {
            return file_get_contents($filePath . '.htm');
        }
        return null;
    }

    /**
     * Selects and formats verses from the cleaned webpage content.
     *
     * @param string $page The cleaned webpage content.
     * @return string The selected verses.
     */
    private function selectVerses($page)
    {

        $page = str_replace(
            ['<!--span class="verse"', '<p>', '</p>', '<br/>', '<br />'],
            ['<span class="verse"', '', '', '<br>', '<br>'],
            $page
        );
        $page = str_replace(
            ['<span class="dimver">', '</span-->', "\n", "\r"],
            ['', '</span>', '', ''],
            $page
        );
        $page = str_replace(
            ['  </span>'],
            [''],
            $page
        );
        $lines = explode('<br>', $page);


        $verseRange = range(
            intval($this->bibleReference->getVerseStart()),
            intval($this->bibleReference->getVerseEnd())
        );


        $verses = '';
        foreach ($lines as $line) {
            $verseNum = $this->extractVerseNumber($line);
            if (in_array($verseNum, $verseRange)) {
                $verses .= $this->formatVerseLine($verseNum, $line);
            }
        }


        return $verses;
    }

    /**
     * Cleans a segment of HTML content between specific markers.
     *
     * @param string $webpage The HTML content to clean.
     * @return string The cleaned content.
     */
    private function trimToChapter($webpage)
    {
        $startMarker = '<!--... the Word of God:-->';
        $endMarker = '<!--... sharper than any twoedged sword... -->';
        $startPos = strpos($webpage, $startMarker) + strlen($startMarker);
        $endPos = strpos($webpage, $endMarker);
        $chapter = substr($webpage, $startPos, $endPos - $startPos);
        return $chapter;
    }



    /**
     * Extracts and formats a single verse line.
     *
     * @param int $verseNum The verse number.
     * @param string $line The verse line content.
     * @return string The formatted verse line.
     */
    private function formatVerseLine($verseNum, $line)
    {
        // Find the last occurrence of </span>
        $lastSpanPos = strripos($line, '</span>');

        if ($lastSpanPos !== false) {
            // Extract the content after the last </span>
            $verseText = substr($line, $lastSpanPos + strlen('</span>'));
        } else {
            // If no </span> is found, assume the entire line is the verse text
            $verseText = $line;
        }

        // Return the formatted line
        return '<p><sup>' . $verseNum . '</sup>' . $verseText . '</p>' . "\n";
    }

    /**
     * Extracts the local language reference from the webpage.
     *
     * @param string $webpage The HTML content.
     * @return string The extracted reference language.
     */
    private function extractReferenceLanguage($webpage)
    {
        $find = '<p class="ym-noprint">';
        $posStart = strpos($webpage, $find) + strlen($find);
        $posEnd = strpos($webpage, ':', $posStart);
        $bookName = trim(substr($webpage, $posStart, $posEnd - $posStart));

        $verses = $this->bibleReference->getChapterStart() . ':' .
            $this->bibleReference->getVerseStart() . '-' .
            $this->bibleReference->getVerseEnd();

        return $bookName . ' ' . $verses;
    }

    /**
     * Extracts the verse number from a line of text.
     *
     * @param string $line The line containing the verse.
     * @return int The extracted verse number.
     */
    private function extractVerseNumber($line)
    {
        // Find the position of the last '</span>'
        $endPos = strripos($line, '</span>'); // Use strripos for the last occurrence
        if ($endPos === false) {
            return 0; // Return 0 if no closing </span> found
        }

        // Find the position of the last '>'
        $startPos = strrpos(substr($line, 0, $endPos), '>'); // Search up to $endPos
        if ($startPos === false) {
            return 0; // Return 0 if no opening '>' found
        }

        // Extract the content between '>' and '</span>'
        $verseNumber = substr($line, $startPos + 1, $endPos - $startPos - 1);

        // Return the integer value of the verse number
        return intval(trim($verseNumber)); // Trim in case of spaces
    }
}
