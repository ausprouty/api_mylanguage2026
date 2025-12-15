<?php

namespace App\Services\BiblePassage;

use App\Factories\BibleGatewayConnectionFactory;
use App\Models\Bible\BibleModel;
use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use App\Configuration\Config;

class BibleGatewayPassageService extends AbstractBiblePassageService
{

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
     * Extract and clean the BibleGateway passage HTML from the fetched page.
     *
     * Goal
     * ----
     * Return a compact HTML fragment that is safe to embed in our UI:
     * - Keep paragraph structure (<p>) and verse numbers (<sup class="versenum">…</sup>)
     * - Remove crossrefs/footnotes/scripts/styles/headers/links and other noise
     * - Prefer returning the "deep content" wrapper (<div class="std-text">…</div>)
     *
     * Source assumptions (BibleGateway)
     * --------------------------------
     * - The main passage wrapper is:   <div class="passage-text">…</div>
     * - Verse numbers are usually:     <sup class="versenum">16</sup>
     * - Cross references section is:   <div class="crossrefs …">…</div>
     * - Main readable text is in:      <div class="std-text">…</div>
     *
     * Notes
     * -----
     * - DOMDocument::saveHTML() often inserts newlines/indentation. We minify by
     *   collapsing whitespace between tags so JSON output won’t contain \n.
     * - We parse into a full DOM, then copy only the passage-text subtree into a new
     *   DOM so cleanup queries cannot accidentally touch unrelated page content.
     */
    public function getPassageText(): string
    {
        // Timing + input size logging helps catch slow parses and unexpected payloads
        $t0 = microtime(true);
        $html = (string)($this->webpage ?? '');
        $inBytes = strlen($html);
        LoggerService::logInfo('PassageText:start', "in_bytes={$inBytes}");

        // No input -> nothing to extract
        if ($inBytes === 0) {
            LoggerService::logError('PassageText:empty', 'No HTML input');
            return '';
        }

        // Suppress libxml warnings (BibleGateway HTML is not strict XML)
        libxml_use_internal_errors(true);

        // Parse flags:
        // - NONET: block network access during parse (safer)
        // - NOERROR/NOWARNING: suppress parser warnings
        // - COMPACT: reduce memory usage
        // - BIGLINES (if available): better line numbers in error reporting
        $flags = LIBXML_NOERROR
            | LIBXML_NOWARNING
            | LIBXML_NONET
            | LIBXML_COMPACT
            | (defined('LIBXML_BIGLINES') ? LIBXML_BIGLINES : 0);

        // 1) Parse the fetched HTML into a DOM
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $ok  = @$dom->loadHTML($html, $flags);

        LoggerService::logInfo(
            'PassageText:parse_ms',
            (string)((microtime(true) - $t0) * 1000)
        );

        if (!$ok) {
            LoggerService::logError(
                'PassageText:parse_fail',
                'DOMDocument->loadHTML failed'
            );
            return '';
        }

        // 2) Locate the passage container in the source DOM
        $xp = new \DOMXPath($dom);
        $nl = $xp->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' passage-text ')]"
        );

        if ($nl->length === 0) {
            LoggerService::logError(
                'PassageText:no_container',
                'div.passage-text not found'
            );
            return '';
        }

        // 3) Copy ONLY the passage subtree into a new DOM so cleanup is scoped
        $newDom = new \DOMDocument('1.0', 'UTF-8');
        $passageDiv = $newDom->importNode($nl->item(0), true);
        $newDom->appendChild($passageDiv);
        $xp2 = new \DOMXPath($newDom);

        // 4) Remove entire blocks/elements we never want in the output
        //    - footnotes + crossrefs: noisy and often empty placeholders
        //    - scripts/styles/templates/noscript: non-content
        //    - h3: section headings like “Cross references”
        foreach ($xp2->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' footnotes ')]"
            . "|//div[contains(concat(' ', normalize-space(@class), ' '), ' crossrefs ')]"
            . "|//script|//style|//noscript|//template|//h3"
        ) as $n) {
            $n->parentNode->removeChild($n);
        }

        // 5) Remove superscripts EXCEPT chapter/verse numbers.
        //    BibleGateway uses <sup class="versenum">…</sup> for verse numbers.
        foreach ($xp2->query(
            "//sup[not(contains(concat(' ', normalize-space(@class), ' '), ' chapternum '))"
            . " and not(contains(concat(' ', normalize-space(@class), ' '), ' versenum '))]"
        ) as $n) {
            $n->parentNode->removeChild($n);
        }

        // 6) Unwrap links:
        //    We do NOT want anchors (clickable links), but we DO want their text.
        foreach ($xp2->query("//a") as $n) {
            $p = $n->parentNode;
            while ($n->firstChild) {
                $p->insertBefore($n->firstChild, $n);
            }
            $p->removeChild($n);
        }

        // 7) Remove HTML comments (BibleGateway leaves markers like <!--end of crossrefs-->)
        foreach ($xp2->query("//comment()") as $n) {
            $n->parentNode->removeChild($n);
        }

        // 8) Remove leftover UI text that can remain after unwrapping links
        foreach ($xp2->query("//text()[normalize-space(.)='Read full chapter']") as $n) {
            $n->parentNode->removeChild($n);
        }

        // 9) Remove empty structural divs that add no value
        foreach ($xp2->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' il-text ')]"
            . "[not(normalize-space()) and not(*)]"
        ) as $n) {
            $n->parentNode->removeChild($n);
        }

        // 10) Unwrap spans to reduce markup noise,
        //     but preserve chapternum/versenum spans if they exist.
        foreach ($xp2->query(
            "//span[not(contains(concat(' ', normalize-space(@class), ' '), ' chapternum '))"
            . " and not(contains(concat(' ', normalize-space(@class), ' '), ' versenum '))]"
        ) as $n) {
            $p = $n->parentNode;
            while ($n->firstChild) {
                $p->insertBefore($n->firstChild, $n);
            }
            $p->removeChild($n);
        }

        // 11) Convert nonstandard <small-caps> tags into a normal <span>
        foreach ($xp2->query('//small-caps') as $n) {
            $span = $newDom->createElement('span');
            $span->setAttribute('class', 'small-caps');
            $span->setAttribute('style', 'font-variant: small-caps');

            while ($n->firstChild) {
                $span->appendChild($n->firstChild);
            }

            $n->parentNode->replaceChild($span, $n);
        }

        // 12) Choose the best root to return:
        //     Prefer div.std-text (BibleGateway’s “real content” wrapper) so we avoid
        //     returning the extra outer div layers (passage-content/version/etc).
        $std = $xp2->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' std-text ')]"
        );
        $root = ($std->length > 0) ? $std->item(0) : $newDom->documentElement;

        // 13) Serialize HTML and minify whitespace inserted by DOMDocument
        $out = $newDom->saveHTML($root);

        // Collapse whitespace/newlines BETWEEN tags (keeps text spacing intact)
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
