<?php

namespace App\Services\BiblePassage;

use App\Configuration\Config;
use App\Factories\BibleWordConnectionFactory; 
use App\Models\Bible\BibleModel;
use App\Services\LoggerService;
use App\Services\Database\DatabaseService;         

class BibleWordPassageService extends AbstractBiblePassageService
{
    /*
     * NOTE: Only inject the factory here so PHP-DI can autowire safely.
     * BibleModel + DatabaseService are runtime and will be passed via parent.
     */
    public function __construct(
        
        private BibleWordConnectionFactory $wordConnectionService) 
    {}
    /**
     * Helper invoked right after construction to pass runtime deps.
     * (Inherits protected init() from AbstractBiblePassageService if you have it;
     * otherwise keep parent::__construct signature and call it here.)
     */
    public function initRuntime(
        \App\Models\Bible\BibleModel $bible,
        \App\Services\Database\DatabaseService $databaseService
    ): void {
        parent::__construct($bible, $databaseService);
    }

    /** Resolve base URL (DI param key: endpoints.wordproject) */
    private function baseUrl(): string
    {
        return rtrim((string) Config::get('endpoints.wordproject', 'https://wordproject.org/bibles'), '/');
    }

    /** Public chapter URL, e.g. https://wordproject.org/bibles/en/42/7.htm */
    public function getPassageUrl(): string
    {
        return $this->baseUrl()
            . '/' . $this->bible->getExternalId()
            . '/' . $this->formatChapterPage() . '.htm';
    }

    /**
     * Get the raw HTML, preferring local cache when present.
     * Returns ['<html>'] and sets $this->webpage for later use.
     */
    public function getWebPage(): array
    {
        $webpage = [];
        $local = $this->generateFilePath();
        LoggerService::logInfo('BibleWordPassageService-local', $local);

        if (is_file($local)) {
            LoggerService::logInfo('BibleWordPassageService-cache', "$local exists");
            $webpage[0] = $this->fetchFromFileDirectory($local);
        } else {
            LoggerService::logInfo('BibleWordPassageService-cache', "$local does NOT exist");

            // Build endpoint path only; the connection service adds the base URL
            $endpoint = $this->bible->getExternalId()
                . '/' . $this->formatChapterPage() . '.htm';

            // ✅ use factory (autoFetch=true, salvageJson=false for HTML pages)
            $conn = $this->wordConnectionService->fromPath($endpoint, autoFetch: true, salvageJson: false);
            $body = $conn->getBody();

            if ($body === '') {
                $msg = 'Empty response from WordProject: ' . $this->getPassageUrl();
                LoggerService::logError('BibleWordPassageService-http', $msg);
                throw new \RuntimeException($msg);
            }
            $webpage[0] = $body;
        }

        $this->webpage = $webpage;
        return $webpage;
    }

    /** Absolute local file path for cached HTML. */
    private function generateFilePath(): string
    {
        $root = rtrim(Config::getDir('resources.root'), "/\\"); // .../Resources
        $lang = $this->bible->getExternalId();                  // e.g. "en"
        $rel  = 'bibles/wordproject/' . $lang . '/' . $this->formatChapterPage() . '.html';

        $path = $root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, "/\\"));

        LoggerService::logInfo('BibleWordPassageService-path', $path);
        return $path;
    }

    /** "42/7" */
    private function formatChapterPage(): string
    {
        $book = str_pad(
            (string) (int) $this->passageReference->getBookNumber(),
            2,
            '0',
            STR_PAD_LEFT
        );
        $chapter = (int) $this->passageReference->getChapterStart();
        return $book . '/' . $chapter;
    }

    /** Read a local cached HTML file. */
    private function fetchFromFileDirectory(string $filename): string
    {
        LoggerService::logInfo('BibleWordPassageService-readfile', $filename);
        return (string) file_get_contents($filename);
    }

    /** Extract selected verses as HTML <p><sup>N</sup>…</p> */
    public function getPassageText(): string
    {
        $html = $this->webpage[0] ?? '';
        if ($html === '') return '';

        $chapter = $this->trimToChapter($html);
        return $this->trimToVerses($chapter);
    }

    private function trimToChapter(string $pageHtml): string
    {
        $startMarker = '<!--... the Word of God:-->';
        $endMarker   = '<!--... sharper than any twoedged sword... -->';

        $startPos = strpos($pageHtml, $startMarker);
        $endPos   = strpos($pageHtml, $endMarker);

        if ($startPos === false || $endPos === false || $endPos <= $startPos) {
            return $pageHtml; // fallback
        }
        $startPos += strlen($startMarker);
        return substr($pageHtml, $startPos, $endPos - $startPos);
    }

    private function trimToVerses(string $chapterHtml): string
    {
        LoggerService::logInfo('BibleWordPassageService-chapter', 'len=' . strlen($chapterHtml));
        $selected = $this->selectVerses($chapterHtml);
        return $selected === '' ? '' : "\n<!-- begin bible -->{$selected}\n<!-- end bible -->\n";
    }

    private function selectVerses(string $page): string
    {
        $page = str_replace(
            ['<!--span class="verse"', '<p>', '</p>', '<br/>', '<br />'],
            ['<span class="verse"',   '',    '',     '<br>',  '<br>'],
            $page
        );

        $vStart = (int) $this->passageReference->getVerseStart();
        $vEnd   = (int) $this->passageReference->getVerseEnd();
        $range  = range($vStart, $vEnd);

        $out = '';
        foreach (explode('<br>', $page) as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $n = $this->extractVerseNumber($line);
            if ($n !== 0 && in_array($n, $range, true)) {
                $out .= $this->formatVerseLine($n, $line);
            }
        }
        return $out;
    }

    private function formatVerseLine(int $verseNum, string $line): string
    {
        $lastSpan = strripos($line, '</span>');
        $verseText = $lastSpan !== false ? substr($line, $lastSpan + 7) : $line;
        return '<p><sup>' . $verseNum . '</sup>' . $verseText . "</p>\n";
    }

    private function extractVerseNumber(string $line): int
    {
        $end = strripos($line, '</span>');
        if ($end === false) return 0;
        $start = strrpos(substr($line, 0, $end), '>');
        if ($start === false) return 0;
        return (int) trim(substr($line, $start + 1, $end - $start - 1));
    }

    /** Local-language book title from <title> + verse range. */
    public function getReferenceLocalLanguage(): string
    {
        $html = $this->webpage[0] ?? '';
        if ($html === '') {
            return $this->passageReference->getVerseStart() . '-' . $this->passageReference->getVerseEnd();
        }

        preg_match('/<title>(.*?)<\/title>/i', $html, $m);
        $title = $m[1] ?? '';

        preg_match('/^([^\d]+)/u', $title, $m2);
        $book = isset($m2[1]) ? trim($m2[1]) : '';

        return $book . ':' . $this->passageReference->getVerseStart() . '-' . $this->passageReference->getVerseEnd();
    }
}
