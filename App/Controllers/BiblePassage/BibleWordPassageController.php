<?php

namespace App\Controllers\BiblePassage;

use App\Configuration\Config;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Repositories\BiblePassageRepository;
use App\Services\LoggerService;
use App\Services\Web\BibleWordConnectionService;

class BibleWordPassageController
{
    private $bible;
    private $bibleReference;

    /**
     * Constructor to initialize dependencies.
     *
     * @param PassageReferenceModel $bibleReference
     * @param BibleModel $bible

     */
    public function __construct(
        PassageReferenceModel $bibleReference,
        BibleModel $bible,
  
    ) {
       
        $this->bibleReference = $bibleReference;
        $this->bible = $bible;
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


    /**
     * Fetches content from an external source using a web service.
     *
     * @return PassageModel The Bible passage model with data.
     */
    public function fetchFromWeb()
    {
        $passageModel = new PassageModel();
        $endpoint = $this->bible->getExternalId() . '/'
            . $this->formatChapterPage() . '.htm';

        $webpage = new BibleWordConnectionService($endpoint);

        if (!$webpage->response) {
            LoggerService::logError('BibleWordPassageController-140', 'Failed to fetch Bible passage from WordProject.');
            return $passageModel;
        }
        $text = $this->trimToVerses($webpage->response);
        if (!$text) {
            LoggerService::logError('BibleWordPassageController-145', 'Unable to extract Bible Word Text.');
            return $passageModel;
        }
        $passageModel->setPassageText($text);
        $passageModel->setReferenceLocalLanguage(
            $this->extractReferenceLanguage($webpage->response)
        );


        return $passageModel;
    }

    /**
     * Fetches content from a local server file.
     *
     * @return PassageModel The Bible passage model with data.
     */
    public function fetchFromServerFile()
    {
        $filePath = $this->generateFilePath();
        $webpage = $this->loadWebpageContent($filePath);
        $passageModel = new PassageModel();

        $text = $this->trimToVerses($webpage);
        if ($text) {
            $passageModel->setPassageText($text);
            $passageModel->setReferenceLocalLanguage(
                $this->extractReferenceLanguage($webpage)
            );
        }

        return $passageModel;
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
    private function loadWebpageContent($filePath)
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
}
