<?php

namespace App\Services\BiblePassage;

use App\Factories\YouVersionConnectionFactory;      // ⬅ inject the factory
use App\Models\Bible\BibleModel;
use App\Services\BiblePassage\AbstractBiblePassageService;
use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use App\Services\Web\YouVersionConnectionService;

class YouVersionPassageService extends AbstractBiblePassageService
{

    /**
     * NOTE: Only inject the factory here so PHP-DI can autowire safely.
     * BibleModel + DatabaseService are runtime and will be passed via parent.
     */
    public function __construct(     
        private YouVersionConnectionFactory $youVersionConnectionFactory
    ){}

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
    /** Build the public URL (absolute). */
    public function getPassageUrl(): string
    {
        $path = $this->buildEndpointPath(); // relative part like "111/JHN.3.16-18.NIV"
        $base = rtrim(YouVersionConnectionService::getBaseUrl(), '/'); // from Config endpoints.youVersionConnectionFactoryersion
        $url  = $base . '/' . ltrim($path, '/');

        return $url;
    }

    /**
     * Fetch the web page HTML. Sets $this->webpage[0] to the HTML string.
     * Returns an array with the HTML at index 0 (keeps your older calling convention).
     */
    public function getWebPage(): array
    {
        $endpoint = $this->buildEndpointPath(); // ✅ endpoint only
        // YouVersion serves HTML → salvageJson=false
        $conn = $this->youVersionConnectionFactory->fromPath($endpoint, autoFetch: true, salvageJson: false);

        $html = $conn->getBody();
        if ($html === '') {
            $msg = 'Empty response from YouVersion: ' . $this->getPassageUrl();
            LoggerService::logError('YouVersionPassageService-http', $msg);
            throw new \RuntimeException($msg);
        }

        $this->webpage = [$html];
        return $this->webpage;
    }

    /**
     * Extract the passage text from the page’s embedded JSON.
     * Sets $this->referenceLocalLanguage from the JSON too.
     */
    public function getPassageText(): string
    {
        $html = $this->webpage[0] ?? '';
        if ($html === '') {
            LoggerService::logError('YouVersionPassageService', 'No HTML loaded');
            return '';
        }

        $posStart = strpos($html, 'verses":[{"reference"');
        $posEnd   = ($posStart !== false) ? strpos($html, '"twitterCard":', $posStart) : false;

        if ($posStart === false || $posEnd === false) {
            LoggerService::logError('YouVersionPassageService', 'Could not locate embedded JSON');
            return '';
        }

        $length = $posEnd - $posStart - 1;
        $jsonFrag = '{"' . substr($html, $posStart, $length) . '}';

        $data = json_decode($jsonFrag);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data->verses[0])) {
            LoggerService::logError('YouVersionPassageService', 'Invalid JSON: ' . json_last_error_msg());
            return '';
        }

        $this->referenceLocalLanguage = (string)($data->verses[0]->reference->human ?? '');
        return (string)($data->verses[0]->content ?? '');
    }

    public function getReferenceLocalLanguage(): string
    {
        return $this->referenceLocalLanguage;
    }

    /** Build the relative endpoint path YouVersion expects after /bible/. */
    private function buildEndpointPath(): string
    {
        $bookId   = $this->passageReference->getUversionBookID(); // e.g. "JHN"
        $chapter  = $this->passageReference->getChapterStart();
        $vStart   = $this->passageReference->getVerseStart();
        $vEnd     = $this->passageReference->getVerseEnd();

        // externalId has a "%" placeholder for "{BOOK}.{CH:VS-Ve}.{VERSION}", e.g. "111/JHN.%.NIV"
        $filled   = str_replace('%', "{$bookId}.{$chapter}.{$vStart}-{$vEnd}", $this->bible->getExternalId());

        // Encode the last segment after the final dot (YouVersion style)
        $dot = strrpos($filled, '.');
        if ($dot !== false) {
            $before = substr($filled, 0, $dot + 1);
            $after  = substr($filled, $dot + 1);
            $filled = $before . rawurlencode($after);
        }

        // Return only the path portion that follows ".../bible/"
        // If someone stored a full absolute URL by mistake, strip scheme/host.
        if (preg_match('#https?://[^/]+/bible/(.+)$#i', $filled, $m)) {
            return $m[1];
        }
        return ltrim($filled, '/');
    }
}
