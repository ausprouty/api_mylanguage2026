<?php

namespace App\Controllers\BiblePassage\BibleGateway;

use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Models\Bible\BibleModel;
use App\Repositories\BiblePassageRepository;
use App\Services\Web\BibleGatewayConnectionService;
use App\Services\LoggerService;

/**
 * Controller to fetch Bible passages from BibleGateway
 * and save them to the database.
 */
class BibleGatewayPassageController
{
    private $biblePassageRepository;
    private $bibleReference;
    private $bible;

    public function __construct(
        PassageReferenceModel $bibleReference,
        BibleModel $bible,
        BiblePassageRepository $biblePassageRepository
    ) {
        $this->biblePassageRepository = $biblePassageRepository;
        $this->bibleReference = $bibleReference;
        $this->bible = $bible;
    }

    /**
     * Fetches a Bible passage from BibleGateway and saves it.
     */
    public function fetchAndSavePassage(): PassageModel
    {
        $t0 = microtime(true);

        $reference = $this->bibleReference->getEntry();
        $version = $this->bible->getExternalId();

        $passageUrl = '/passage/?search='
            . rawurlencode($reference)
            . '&version='
            . rawurlencode($version);

        LoggerService::logInfo(
            'BGPC:fetch:start',
            $passageUrl
        );

        $conn = new BibleGatewayConnectionService($passageUrl);
        $body = (string) $conn->getBody();
        $code = $conn->getHttpCode();
        $ctype = $conn->getContentType();

        LoggerService::logInfo(
            'BGPC:fetch:done',
            "code={$code} bytes=" . strlen($body) . " ctype={$ctype}"
        );

        $passageModel = new PassageModel();

        if ($body !== '') {
            $t1 = microtime(true);
            $cleanHtml = $this->formatExternal($body);
            $t2 = microtime(true);
            $localRef = $this->getReferenceLocalLanguage($body);
            $t3 = microtime(true);

            LoggerService::logInfo(
                'BGPC:timings',
                'format_ms='
                . (int) (($t2 - $t1) * 1000)
                . ' ref_ms='
                . (int) (($t3 - $t2) * 1000)
                . ' total_ms='
                . (int) (($t3 - $t0) * 1000)
            );

            $passageModel->setPassageText($cleanHtml);
            $passageModel->setReferenceLocalLanguage($localRef);
            $passageModel->setPassageUrl($passageUrl);

            $this->biblePassageRepository->savePassageRecord($passageModel);
        } else {
            LoggerService::logError(
                'BGPC:empty_body',
                $passageUrl
            );
        }

        return $passageModel;
    }

    /**
     * Extracts the reference in the local language from the page HTML.
     */
    private function getReferenceLocalLanguage(string $html): string
    {
        $html = preg_replace(
            '~<(script|style|noscript|template)\b[^>]*>.*?</\1>~is',
            '',
            $html
        );

        libxml_use_internal_errors(true);
        $flags = LIBXML_NOERROR
            | LIBXML_NOWARNING
            | LIBXML_NONET
            | LIBXML_COMPACT
            | (defined('LIBXML_BIGLINES') ? LIBXML_BIGLINES : 0);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $ok = @$dom->loadHTML($html, $flags);
        if (!$ok) {
            LoggerService::logError(
                'BGPC:ref:parse_fail',
                'loadHTML'
            );
            return '';
        }

        $xp = new \DOMXPath($dom);
        $queries = [
            "//h1[contains(concat(' ', normalize-space(@class), ' '),"
            . " ' passage-display-bcv ')]",
            "//div[contains(concat(' ', normalize-space(@class), ' '),"
            . " ' passage-display-bcv ')]",
            "//div[contains(concat(' ', normalize-space(@class), ' '),"
            . " ' passage-display ')]",
            "//h1[contains(concat(' ', normalize-space(@class), ' '),"
            . " ' passage-display ')]",
            "//div[contains(concat(' ', normalize-space(@class), ' '),"
            . " ' dropdown-display-text ')]",
        ];

        foreach ($queries as $q) {
            $nl = $xp->query($q);
            if ($nl && $nl->length > 0) {
                $txt = trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $nl->item(0)->textContent ?? ''
                    )
                );
                if ($txt !== '') {
                    return $txt;
                }
            }
        }

        return '';
    }

    /**
     * Formats external HTML into cleaned passage HTML.
     */
    private function formatExternal(string $html): string
    {
        $t0 = microtime(true);

        // Strip heavy blocks before parsing.
        $html = preg_replace(
            '~<(script|style|noscript|template)\b[^>]*>.*?</\1>~is',
            '',
            $html
        );

        libxml_use_internal_errors(true);
        $flags = LIBXML_NOERROR
            | LIBXML_NOWARNING
            | LIBXML_NONET
            | LIBXML_COMPACT
            | (defined('LIBXML_BIGLINES') ? LIBXML_BIGLINES : 0);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $ok = @$dom->loadHTML($html, $flags);
        if (!$ok) {
            LoggerService::logError(
                'BGPC:format:parse_fail',
                'loadHTML'
            );
            return '';
        }

        $xp = new \DOMXPath($dom);

        // Find the passage container.
        $nl = $xp->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '),"
            . " ' passage-text ')]"
        );
        if (!$nl || $nl->length === 0) {
            LoggerService::logError(
                'BGPC:format:no_passage',
                'div.passage-text not found'
            );
            return '';
        }

        // Work in a minimal DOM containing only the passage element.
        $min = new \DOMDocument('1.0', 'UTF-8');
        $root = $min->importNode($nl->item(0), true);
        $min->appendChild($root);
        $xp2 = new \DOMXPath($min);

        // Remove footnotes (if any within this subtree).
        foreach ($xp2->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '),"
            . " ' footnotes ')]"
        ) as $n) {
            $n->parentNode->removeChild($n);
        }

        // Remove superscripts and headings.
        foreach ($xp2->query('//sup|//h3') as $n) {
            $n->parentNode->removeChild($n);
        }

        // Remove links entirely.
        foreach ($xp2->query('//a') as $n) {
            $n->parentNode->removeChild($n);
        }

        // Unwrap spans except .chapternum / .versenum.
        $spans = $xp2->query(
            "//span[not(contains(concat(' ', normalize-space(@class), ' '),"
            . " ' chapternum ')) and not(contains(concat(' ',"
            . " normalize-space(@class), ' '), ' versenum '))]"
        );
        foreach ($spans as $n) {
            $parent = $n->parentNode;
            while ($n->firstChild) {
                $parent->insertBefore($n->firstChild, $n);
            }
            $parent->removeChild($n);
        }

        // <small-caps> → styled span.small-caps.
        foreach ($xp2->query('//small-caps') as $n) {
            $span = $min->createElement('span');
            $span->setAttribute('class', 'small-caps');
            $span->setAttribute('style', 'font-variant: small-caps');
            while ($n->firstChild) {
                $span->appendChild($n->firstChild);
            }
            $n->parentNode->replaceChild($span, $n);
        }

        $out = $min->saveHTML($min->documentElement);

        LoggerService::logInfo(
            'BGPC:format:done',
            'ms=' . (int) ((microtime(true) - $t0) * 1000)
            . ' bytes=' . strlen($out)
        );

        return $out;
    }
}
