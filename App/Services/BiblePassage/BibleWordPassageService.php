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

    public function getReferenceLocalLanguage(): string
    {
        $html = $this->webpage[0] ?? '';

        $vs = (int) $this->passageReference->getVerseStart();
        $ve = (int) $this->passageReference->getVerseEnd();

        // Safe fallback if we can't read HTML/title
        if ($html === '' || !preg_match('/<title>(.*?)<\/title>/iu', $html, $m)) {
            return $vs . '-' . $ve;
        }

        $title = trim($m[1] ?? '');
        if ($title === '') {
            return $vs . '-' . $ve;
        }

        // ------------------------------------------------------------
        // 1) Remove translation/publisher suffixes and keep book+chapter
        // ------------------------------------------------------------

        // If there's a comma, keep the part AFTER the last comma
        // e.g. "Krishti Shpëtimtari , GJONI 3" -> "GJONI 3"
        if (preg_match('/[,，]/u', $title)) {
            $parts = preg_split('/[,，]/u', $title);
            $title = trim(end($parts));
        }

        // If there's a semicolon, drop everything after it
        // e.g. "3GJONI 1 ; Bibël - Dhjata e Re" -> "3GJONI 1"
        $title = preg_replace('/\s*[;；].*$/u', '', $title);
        $title = trim($title);

        // Normalize "3GJONI" -> "3 GJONI" (digit glued to letters)
        $title = preg_replace('/^(\d)(\p{L})/u', '$1 $2', $title);

        // ------------------------------------------------------------
        // 2) Extract BOOK + CHAPTER from known title patterns
        // ------------------------------------------------------------

        // Korean-style: "예레미야 애가 1: 성경 - 구약 성서"
        // => book="예레미야 애가", ch=1
        if (preg_match('/^(.+?)\s+(\d+)\s*[:：]/u', $title, $m2)) {
            $book = trim($m2[1]);
            $ch   = (int) $m2[2];
            return $book . ' ' . $ch . ':' . $vs . '-' . $ve;
        }

        // Portuguese/Spanish/English worded chapter:
        // "Cantares de Salomão capítulo 1"
        // "Cantares de Salomão capitulo 1"
        // "Song of Songs chapter 1"
        if (preg_match(
            '/^(.+?)\s+(?:cap[ií]tulo|chapter)\s+(\d+)\s*$/iu',
            $title,
            $m2
        )) {
            $book = trim($m2[1]);
            $ch   = (int) $m2[2];
            return $book . ' ' . $ch . ':' . $vs . '-' . $ve;
        }

        // Generic: ends with chapter number
        // "GJONI 3", "1 GJONI 5", "3 GJONI 1"
        if (preg_match('/^(.+?)\s+(\d+)\s*$/u', $title, $m2)) {
            $book = trim($m2[1]);
            $ch   = (int) $m2[2];
            return $book . ' ' . $ch . ':' . $vs . '-' . $ve;
        }

        // Last resort: return cleaned title + verses
        return $title . ':' . $vs . '-' . $ve;
    }

}
