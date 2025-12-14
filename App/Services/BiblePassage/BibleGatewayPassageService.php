<?php

namespace App\Services\BiblePassage;

use App\Models\Bible\BibleModel;
use App\Factories\BibleGatewayConnectionFactory; // ⬅ inject this
use App\Services\BiblePassage\AbstractBiblePassageService;
use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use App\Configuration\Config;

class BibleGatewayPassageService extends AbstractBiblePassageService
{
    /** Resolve base URL from config; fallback to public site. */
    public function __construct(
        
        private BibleGatewayConnectionFactory $bibleGatewayConnectionService // ⬅ factory, not connection
    ) {}
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
     * Example: https://www.biblegateway.com/passage/?search=John%203%3A16&version=NIV
     */
    public function getPassageUrl(): string
    {
        
    $reference = trim((string) $this->passageReference->getEntry());
    $version   = trim((string) $this->bible->getExternalId());

    if ($reference === '' || $version === '') {
        throw new InvalidArgumentException('Missing reference or version.');
    }
    $baseUrl = (string) (Config::get('endpoints.biblegateway')
        ?? 'https://www.biblegateway.com');
    $baseUrl = rtrim($baseUrl, '/');

    $query = http_build_query(
        ['search' => $reference, 'version' => $version],
        '', '&', PHP_QUERY_RFC3986
    );

    return $baseUrl . '/passage/?' . $query;

    }

    /**
     * Fetch the HTML for the passage from BibleGateway.
     */
    public function getWebPage(): string
    {
        // Build only the path/query; the factory/service will prepend base URL.
        $endpoint = '/passage/?search='
            . rawurlencode($this->passageReference->getEntry())
            . '&version=' . rawurlencode($this->bible->getExternalId());

        // ✅ use the factory (autoFetch=true, salvageJson=false for HTML)
        $conn = $this->bibleGatewayConnectionService->fromPath($endpoint, autoFetch: true, salvageJson: false);

        $body = $conn->getBody();
        if ($body === '') {
            throw new \RuntimeException("Empty response from BibleGateway: {$endpoint}");
        }
        return $body;
    }

    /**
     * Extract and clean the passage HTML block from the fetched page.
     */
    public function getPassageText(): string
    {
        $t0 = microtime(true);
        $html = (string)($this->webpage ?? '');
        $inBytes = strlen($html);
        LoggerService::logInfo('PassageText:start', "in_bytes={$inBytes}");

        if ($inBytes === 0) {
            LoggerService::logError('PassageText:empty', 'No HTML input');
            return '';
        }

        libxml_use_internal_errors(true);
        $flags = LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_COMPACT | (defined('LIBXML_BIGLINES') ? LIBXML_BIGLINES : 0);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $ok  = @$dom->loadHTML($html, $flags);
        LoggerService::logInfo('PassageText:parse_ms', (string) ((microtime(true) - $t0) * 1000));

        if (!$ok) {
            LoggerService::logError('PassageText:parse_fail', 'DOMDocument->loadHTML failed');
            return '';
        }

        $xp = new \DOMXPath($dom);
        $nl = $xp->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' passage-text ')]");
        if ($nl->length === 0) {
            LoggerService::logError('PassageText:no_container', 'div.passage-text not found');
            return '';
        }

        $newDom = new \DOMDocument('1.0', 'UTF-8');
        $passageDiv = $newDom->importNode($nl->item(0), true);
        $newDom->appendChild($passageDiv);
        $xp2 = new \DOMXPath($newDom);

        foreach ($xp2->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' footnotes ')]"
            . "|//div[contains(concat(' ', normalize-space(@class), ' '), ' crossrefs ')]"
            . "|//script|//style|//noscript|//template|//h3"
        ) as $n) {
            $n->parentNode->removeChild($n);
        }

        // remove <sup> except chapternum/versenum (BibleGateway verse numbers)
        foreach ($xp2->query(
            "//sup[not(contains(concat(' ', normalize-space(@class), ' '), ' chapternum '))"
            . " and not(contains(concat(' ', normalize-space(@class), ' '), ' versenum '))]"
        ) as $n) {
            $n->parentNode->removeChild($n);
        }

        // unwrap <a> (keep text/children, drop link)
        foreach ($xp2->query("//a") as $n) {
            $p = $n->parentNode;
            while ($n->firstChild) {
                $p->insertBefore($n->firstChild, $n);
            }
            $p->removeChild($n);
        }

        // remove HTML comments like <!--end of crossrefs-->
        foreach ($xp2->query("//comment()") as $n) {
            $n->parentNode->removeChild($n);
        }

        // remove stray "Read full chapter" text nodes (left behind after unwrapping <a>)
        foreach ($xp2->query("//text()[normalize-space(.)='Read full chapter']") as $n) {
            $n->parentNode->removeChild($n);
        }

        // remove empty il-text blocks
        foreach ($xp2->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' il-text ')][not(normalize-space()) and not(*)]"
        ) as $n) {
            $n->parentNode->removeChild($n);
        }

        // unwrap spans except chapternum/versenum
        foreach ($xp2->query("//span[not(contains(concat(' ', normalize-space(@class), ' '), ' chapternum ')) and not(contains(concat(' ', normalize-space(@class), ' '), ' versenum '))]") as $n) {
            $p = $n->parentNode;
            while ($n->firstChild) $p->insertBefore($n->firstChild, $n);
            $p->removeChild($n);
        }

        // <small-caps> -> <span style="font-variant: small-caps">
        foreach ($xp2->query('//small-caps') as $n) {
            $span = $newDom->createElement('span');
            $span->setAttribute('class', 'small-caps');
            $span->setAttribute('style', 'font-variant: small-caps');
            while ($n->firstChild) $span->appendChild($n->firstChild);
            $n->parentNode->replaceChild($span, $n);
        }

       $root = $newDom->documentElement; // div.passage-text

        // Return inner HTML (strip the outer wrapper div)
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $newDom->saveHTML($child);
        }

        // Minify whitespace added by DOMDocument (newlines/indentation)
        $out = preg_replace("/>\s+</u", "><", $out);
        $out = trim($out);

        LoggerService::logInfo(
            'PassageText:done',
            "ms=" . (int)((microtime(true) - $t0) * 1000)
            . " out_bytes=" . strlen($out)
        );

        $std = $xp2->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' std-text ')]"
        );

        $root = ($std->length > 0) ? $std->item(0) : $newDom->documentElement;
        // pick the deepest “real content” div
        // Return HTML for that node (keep std-text wrapper)
        $out = $newDom->saveHTML($root);

        // Minify whitespace/newlines added by DOMDocument
        $out = preg_replace("/>\s+</u", "><", $out);
        $out = trim($out);

        LoggerService::logInfo(
            'PassageText:done',
            "ms=" . (int)((microtime(true) - $t0) * 1000)
            . " out_bytes=" . strlen($out)
        );

        return $out;

    }

    public function getReferenceLocalLanguage(): string
    {
        $t0 = microtime(true);
        $html = (string)($this->webpage ?? '');
        if ($html === '') {
            LoggerService::logError('RefLocal:empty', 'No HTML input');
            return '';
        }

        $html = preg_replace('~<(script|style|noscript|template)\b[^>]*>.*?</\1>~is', '', $html);

        libxml_use_internal_errors(true);
        $flags = LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_COMPACT | (defined('LIBXML_BIGLINES') ? LIBXML_BIGLINES : 0);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $ok  = @$dom->loadHTML($html, $flags);
        LoggerService::logInfo('RefLocal:parse_ms', (string)((microtime(true) - $t0) * 1000));

        if (!$ok) {
            LoggerService::logError('RefLocal:parse_fail', 'DOMDocument->loadHTML failed');
            return '';
        }

        $xp = new \DOMXPath($dom);
        $xpaths = [
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' passage-display ')]",
            "//h1[contains(concat(' ', normalize-space(@class), ' '), ' passage-display ')]",
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' dropdown-display-text ')]",
        ];

        foreach ($xpaths as $q) {
            $nl = $xp->query($q);
            if ($nl && $nl->length > 0) {
                $txt = trim(preg_replace('/\s+/u', ' ', $nl->item(0)->textContent ?? ''));
                if ($txt !== '') return $txt;
            }
        }

        if (preg_match('~<(?:div|h1)[^>]*class="[^"]*(?:passage-display|dropdown-display-text)[^"]*"[^>]*>(.*?)</(?:div|h1)>~is', $html, $m)) {
            $text = strip_tags($m[1]);
            return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        return '';
    }
}
