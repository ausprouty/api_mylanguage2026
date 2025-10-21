<?php

namespace App\Services\BiblePassage;

use App\Factories\BibleBrainConnectionFactory;
//use App\Models\Bible\BibleModel;
use App\Services\BiblePassage\AbstractBiblePassageService;
//use App\Services\Database\DatabaseService;
use App\Services\LoggerService;

/**
 * BibleBrainPassageService retrieves and formats Bible passage data from the
 * Bible Brain API.
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

        $params = [
            'verse_start' => $this->passageReference->getVerseStart(),
            'verse_end'   => $this->passageReference->getVerseEnd(),
        ];

        // ✅ build connection via factory (adds v/key/format from config)
        $conn = $this->bibleConnectionFactory->fromPath($endpoint, $params);
        LoggerService::logDebug('BibleBrainPassageService-71', $conn);

        $json = $conn->getJson();

        // API usually returns {"data":[ ... ]}; fall back to root for safety
        $data = $json['data'] ?? (is_object($json) ? ($json->data ?? $json) : $json);

        // keep old $this->webpage expectation
        $this->webpage = is_array($data) ? $data : (array) $data;

        return $this->webpage;
    }

    public function getPassageText(): string
    {
        $items = $this->webpage ?? [];
        $out = '';

        foreach ($items as $item) {
            // allow array or object
            $vs = is_array($item) ? ($item['verse_start'] ?? null) : ($item->verse_start ?? null);
            $ve = is_array($item) ? ($item['verse_end']   ?? null) : ($item->verse_end   ?? null);
            $vt = is_array($item) ? ($item['verse_text']  ?? '')   : ($item->verse_text  ?? '');

            if ($vs === null) {
                continue;
            }
            $num = ($ve === null || (string)$vs === (string)$ve) ? $vs : "{$vs}-{$ve}";
            $out .= '<p><sup class="versenum">'.$num.'</sup>'.$vt.'</p>';
        }

        return $out;
    }

    public function getReferenceLocalLanguage(): string
    {
        $first = $this->webpage[0] ?? null;
        $book  = is_array($first) ? ($first['book_name_alt'] ?? null) : ($first->book_name_alt ?? null);

        if ($book) {
            return $book.' '
                .$this->passageReference->getChapterStart().':'
                .$this->passageReference->getVerseStart().'-'
                .$this->passageReference->getVerseEnd();
        }
        return 'Unknown Reference';
    }
}
