<?php

declare(strict_types=1);

namespace App\Services\BiblePassage;

use App\Configuration\Config;
use App\Factories\BibleGatewayConnectionFactory;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Services\LoggerService;
use DOMDocument;
use DOMXPath;

final class BibleGatewayPassageService implements BiblePassageProviderInterface
{
    public function __construct(
        private BibleGatewayConnectionFactory $connectionFactory
    ) {
    }

    /**
     * Fetch a BibleGateway passage.
     *
     * No BPID generation, database operations,
     * caching, or numeral localisation.
     */
    public function fetchPassage(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): PassageModel {
        $passage = new PassageModel();

        // Build the public Read More URL.
        $url = $this->buildPassageUrl($bible, $reference);
        $passage->setPassageUrl($url);

        // Retrieve the HTML.
        $html = $this->fetchHtml($bible, $reference);

        if ($html === '') {
            return $passage;
        }

        // Extract passage text.
        $text = $this->extractPassageText($html);

        if ($text === '') {
            LoggerService::logError(
                'BibleGatewayPassageService',
                'No passage text: ' . $reference->getEntry()
            );

            return $passage;
        }

        $passage->setPassageText($text);

        // Extract the reference in the local language.
        $localReference = $this->extractLocalReference($html);

        $passage->setReferenceLocalLanguage($localReference);

        return $passage;
    }

    /**
     * Public BibleGateway Read More URL.
     *
     * Example:
     * https://www.biblegateway.com/passage/?search=John%203%3A16&version=NIV
     */
    private function buildPassageUrl(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): string {
        $baseUrl = rtrim(
            (string) Config::get(
                'endpoints.biblegateway',
                'https://www.biblegateway.com'
            ),
            '/'
        );

        $query = http_build_query(
            [
                'search'  => $reference->getEntry(),
                'version' => $bible->getExternalId(),
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return $baseUrl . '/passage/?' . $query;
    }

    /**
     * Retrieve HTML from BibleGateway.
     */
    private function fetchHtml(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): string {
        $endpoint = '/passage/?search='
            . rawurlencode($reference->getEntry())
            . '&version='
            . rawurlencode($bible->getExternalId());

        $conn = $this->connectionFactory->fromPath(
            $endpoint,
            autoFetch: true,
            salvageJson: false
        );

        $html = $conn->getBody();

        if ($html === '') {
            throw new \RuntimeException(
                'Empty response from BibleGateway: ' . $endpoint
            );
        }

        return $html;
    }

    /**
     * Extract and clean Bible passage HTML.
     *
     * Preserves:
     * - Paragraph structure
     * - Chapter and verse numbers
     * - Small caps
     *
     * Removes:
     * - Footnotes and cross-references
     * - Scripts and styles
     * - Hyperlinks (but keeps their text)
     * - Unnecessary headings and wrappers
     */
    private function extractPassageText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $previousErrors = libxml_use_internal_errors(true);

        try {
            $flags = LIBXML_NOERROR
                | LIBXML_NOWARNING
                | LIBXML_NONET
                | LIBXML_COMPACT;

            $dom = new DOMDocument('1.0', 'UTF-8');

            if (!@$dom->loadHTML($html, $flags)) {
                return '';
            }

            $xpath = new DOMXPath($dom);

            // Find BibleGateway's main passage container.
            $nodes = $xpath->query(
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' passage-text ')]"
            );

            if (!$nodes || $nodes->length === 0) {
                LoggerService::logError(
                    'BibleGatewayPassageService',
                    'passage-text container not found'
                );

                return '';
            }

            // Copy the passage into its own document.
            $cleanDom = new DOMDocument('1.0', 'UTF-8');

            $passageNode = $cleanDom->importNode(
                $nodes->item(0),
                true
            );

            $cleanDom->appendChild($passageNode);

            $xp = new DOMXPath($cleanDom);

            // Remove unwanted blocks.
            $this->removeNodes(
                $xp,
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' footnotes ')]"
                . "|//div[contains(concat(' ', normalize-space(@class), ' '), ' crossrefs ')]"
                . "|//script|//style|//noscript|//template|//h3"
            );

            // Remove inline reference headings.
            $this->removeNodes(
                $xp,
                "//h4[contains(concat(' ', normalize-space(@class), ' '), ' reference ')]"
            );

            // Remove superscripts except verse/chapter numbers.
            $this->removeNodes(
                $xp,
                "//sup[not(contains(concat(' ', normalize-space(@class), ' '), ' chapternum '))"
                . " and not(contains(concat(' ', normalize-space(@class), ' '), ' versenum '))]"
            );

            // Unwrap hyperlinks but preserve their text.
            $this->unwrapNodes($xp, '//a');

            // Remove HTML comments.
            $this->removeNodes($xp, '//comment()');

            // Remove leftover navigation text.
            $this->removeNodes(
                $xp,
                "//text()[normalize-space(.)='Read full chapter']"
            );

            // Remove empty structural divs.
            $this->removeNodes(
                $xp,
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' il-text ')]"
                . "[not(normalize-space()) and not(*)]"
            );

            // Remove unnecessary spans while preserving verse markers.
            $this->unwrapNodes(
                $xp,
                "//span[not(contains(concat(' ', normalize-space(@class), ' '), ' chapternum '))"
                . " and not(contains(concat(' ', normalize-space(@class), ' '), ' versenum '))]"
            );

            // Convert small-caps elements.
            $smallCaps = $xp->query('//small-caps');

            if ($smallCaps) {
                foreach (iterator_to_array($smallCaps) as $node) {
                    $span = $cleanDom->createElement('span');

                    $span->setAttribute('class', 'small-caps');
                    $span->setAttribute(
                        'style',
                        'font-variant: small-caps'
                    );

                    while ($node->firstChild) {
                        $span->appendChild($node->firstChild);
                    }

                    $node->parentNode?->replaceChild($span, $node);
                }
            }

            // Prefer the actual text wrapper.
            $std = $xp->query(
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' std-text ')]"
            );

            $root = ($std && $std->length > 0)
                ? $std->item(0)
                : $cleanDom->documentElement;

            if (!$root) {
                return '';
            }

            $output = $cleanDom->saveHTML($root);

            if ($output === false) {
                return '';
            }

            // Remove whitespace inserted between HTML tags.
            $output = preg_replace(
                '/>\s+</u',
                '><',
                $output
            );

            return trim($output ?? '');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }

    /**
     * Remove selected DOM nodes.
     */
    private function removeNodes(
        DOMXPath $xpath,
        string $query
    ): void {
        $nodes = $xpath->query($query);

        if (!$nodes) {
            return;
        }

        foreach (iterator_to_array($nodes) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * Remove the specified tags but preserve their children.
     */
    private function unwrapNodes(
        DOMXPath $xpath,
        string $query
    ): void {
        $nodes = $xpath->query($query);

        if (!$nodes) {
            return;
        }

        foreach (iterator_to_array($nodes) as $node) {
            $parent = $node->parentNode;

            if (!$parent) {
                continue;
            }

            while ($node->firstChild) {
                $parent->insertBefore(
                    $node->firstChild,
                    $node
                );
            }

            $parent->removeChild($node);
        }
    }

    /**
     * Extract the local-language reference.
     *
     * Looks for BibleGateway's passage heading.
     */
    private function extractLocalReference(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $previousErrors = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument('1.0', 'UTF-8');

            $cleanHtml = preg_replace(
                '~<(script|style|noscript|template)\b[^>]*>.*?</\1>~is',
                '',
                $html
            );

            $flags = LIBXML_NOERROR
                | LIBXML_NOWARNING
                | LIBXML_NONET
                | LIBXML_COMPACT;

            if (!@$dom->loadHTML($cleanHtml ?? '', $flags)) {
                return '';
            }

            $xpath = new DOMXPath($dom);

            $queries = [
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' passage-display ')]",
                "//h1[contains(concat(' ', normalize-space(@class), ' '), ' passage-display ')]",
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' dropdown-display-text ')]",
            ];

            foreach ($queries as $query) {
                $nodes = $xpath->query($query);

                if (!$nodes || $nodes->length === 0) {
                    continue;
                }

                $text = trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $nodes->item(0)->textContent ?? ''
                    ) ?? ''
                );

                if ($text !== '') {
                    return $text;
                }
            }

            return '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }
}
