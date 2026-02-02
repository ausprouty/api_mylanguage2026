<?php

namespace App\Services\BiblePassage;

use App\Factories\BibleBrainConnectionFactory;
use App\Services\BiblePassage\AbstractBiblePassageService;
use App\Services\LoggerService;

/**
 * BibleBrainPassageService retrieves and formats Bible passage data from the
 * Bible Brain API.
 * It will create endpoints like:
 *    https://4.dbt.io/api/bibles/filesets/MLYBSMN_ET/LUK/18?verse_start=9&verse_end=17&v=4&key=YOUR_KEY
 * Keep parity with BibleWordPassageService: we must receive the
 * BibleModel (so $this->bible is initialised) and the DatabaseService
 * (for any local caching/helpers in AbstractBiblePassageService).
*/
class BibleBrainPassageService extends AbstractBiblePassageService
{
        /**
     * NOTE: Only inject the factory here so PHP-DI can autowire safely.
     * BibleModel + DatabaseService are runtime and will be passed via parent.
     */
    public function __construct(
        private BibleBrainConnectionFactory $bibleConnectionFactory)
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
 
    /**
     * Example: https://live.bible.is/bible/AC1IBS/GEN/1
     */
    public function getPassageUrl(): string
    {
        $passageUrl = 'https://live.bible.is/bible/';
        $passageUrl .= $this->bible->getExternalId() . '/';
        $passageUrl .= $this->passageReference->getuversionBookID() . '/';
        $passageUrl .= $this->passageReference->getChapterStart();
        LoggerService::logDebug('BibleBrainPassageService', [
            'passageUrl'=> $passageUrl
        ]);
        return $passageUrl;
    }

    /**
     * Example API: bibles/filesets/:fileset_id/:book/:chapter?verse_start=X&verse_end=Y
     */
    public function getWebPage(): array
    {
        $endpoint = sprintf(
            'bibles/filesets/%s/%s/%d',
            $this->bible->getExternalId(),
            $this->passageReference->getBookID(),
            $this->passageReference->getChapterStart()
        );
        LoggerService::logDebug('BibleBrainPassageService', [
            'endpoint'=> $endpoint
        ]);

        $params = [
            'verse_start' => $this->passageReference->getVerseStart(),
            'verse_end'   => $this->passageReference->getVerseEnd(),
        ];

        // ✅ build connection via factory (adds v/key/format from config)
        $conn = $this->bibleConnectionFactory->fromPath($endpoint, $params);

        // Log what we can about the request/response without assuming methods.
        $diag = [
            'hasConn' => (bool) $conn,
            'class'   => is_object($conn) ? get_class($conn) : gettype($conn),
        ];
        if (is_object($conn)) {
            if (method_exists($conn, 'getFinalUrl')) {
                $diag['finalUrl'] = $conn->getFinalUrl();
            }
            if (method_exists($conn, 'getHttpCode')) {
                $diag['httpCode'] = $conn->getHttpCode();
            }
            if (method_exists($conn, 'getElapsedMs')) {
                $diag['elapsedMs'] = $conn->getElapsedMs();
            }
            if (method_exists($conn, 'getCurlErrno')) {
                $diag['curlErrno'] = $conn->getCurlErrno();
            }
            if (method_exists($conn, 'getCurlError')) {
                $diag['curlError'] = $conn->getCurlError();
            }
            if (method_exists($conn, 'getContentType')) {
                $diag['contentType'] = $conn->getContentType();
            }
        }
        LoggerService::logDebug('BibleBrainPassageService', [
            'conn' => $diag,
        ]);

        $json = $conn->getJson();
        LoggerService::logDebug('BibleBrainPassageService', [
            'json'=> $json
        ]);

        // API usually returns {"data":[ ... ]}; fall back to root for safety
        $data = $json['data'] ?? (is_object($json) ? ($json->data ?? $json) : $json);
        LoggerService::logDebug('BibleBrainPassageService', [
            'data'=> $data
        ]);

        // keep old $this->webpage expectation
        $this->webpage = is_array($data) ? $data : (array) $data;
        LoggerService::logDebug('BibleBrainPassageService', [
            'webpage'=> $this->webpage
        ]);
        return $this->webpage;
    }

    public function getPassageText(): string
    {
        // Ensure we have data. In your logs, webpage was empty and later code
        // assumed index 0 existed.
        if (empty($this->webpage)) {
            $this->getWebPage();
        }

        $items = $this->webpage ?? [];
        $out = '';

        foreach ($items as $item) {
            // allow array or object
            $vs = is_array($item)
                ? ($item['verse_start'] ?? null)
                : ($item->verse_start ?? null);
            $ve = is_array($item)
                ? ($item['verse_end'] ?? null)
                : ($item->verse_end ?? null);
            $vt = is_array($item)
                ? ($item['verse_text'] ?? '')
                : ($item->verse_text ?? '');
            if ($vs === null) {
                continue;
            }

            $num = ($ve === null || (string) $vs === (string) $ve)
                ? $vs
                : "{$vs}-{$ve}";

            $out .= '<p><sup class="versenum">'
                . $num
                . '</sup>'
                . $vt
                . '</p>';
  
        }
        LoggerService::logDebug('BibleBrainPassageService', [
            'out'=> $out
        ]);

        return $out;
    }

    public function getReferenceLocalLanguage(): string
    {
        if (empty($this->webpage)) {
            $this->getWebPage();
        }

        $first = $this->webpage[0] ?? null;
        LoggerService::logDebug('BibleBrainPassageService', [
            'firstItemPresent' => (bool) $first,
        ]);

        $book = is_array($first)
            ? ($first['book_name_alt'] ?? null)
            : (is_object($first) ? ($first->book_name_alt ?? null) : null);

        $verses = $this->passageReference->getChapterStart()
            . ':'
            . $this->passageReference->getVerseStart()
            . '-'
            . $this->passageReference->getVerseEnd();
 
        if ($book) {
            return $book . ' '. $verses;
               
        }
        
        return $verses;
    }
}
