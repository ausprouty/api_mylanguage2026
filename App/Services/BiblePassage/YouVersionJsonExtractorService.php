<?php
declare(strict_types=1);

namespace App\Services\BiblePassage;

// ---- Example usage (adapt to your service) ----
// $blob = $html; // whatever you loaded
// $x = YouVersionJsonExtractor::extractFirstVerse($blob);
// $reference = $x['reference_human'];
// $content = $x['content'];


final class YouVersionJsonExtractorService
{
    /**
     * Extracts the first verse block from a larger JSON-ish string that
     * contains: "verses":[{...}],"version":{...}
     *
     * @return array{reference_human:string, content:string, usfm:array<int,string>}
     */
    public static function extractFirstVerse(string $blob): array
    {
        $versesJson = self::extractJsonValueByKey($blob, '"verses"');
        if ($versesJson === null) {
            return [
                'reference_human' => '',
                'content' => '',
                'usfm' => [],
            ];
        }

        $verses = json_decode($versesJson, true);
        if (!is_array($verses) || !isset($verses[0]) || !is_array($verses[0])) {
            return [
                'reference_human' => '',
                'content' => '',
                'usfm' => [],
            ];
        }

        $ref = $verses[0]['reference']['human'] ?? '';
        $usfm = $verses[0]['reference']['usfm'] ?? [];
        $content = $verses[0]['content'] ?? '';

        return [
            'reference_human' => is_string($ref) ? $ref : '',
            'content' => is_string($content) ? $content : '',
            'usfm' => is_array($usfm) ? array_values(array_filter(
                $usfm,
                static fn($v) => is_string($v)
            )) : [],
        ];
    }

    /**
     * Finds the JSON value (array/object/string/number/bool/null) for a key
     * inside a larger string. For example:
     *   key: "verses"
     *   matches: "verses":[ ... ]   and returns the "[ ... ]" part.
     */
    private static function extractJsonValueByKey(
        string $s,
        string $quotedKey
    ): ?string {
        $pos = strpos($s, $quotedKey);
        if ($pos === false) {
            return null;
        }

        $i = $pos + strlen($quotedKey);

        // Skip whitespace, then require colon
        $i = self::skipWs($s, $i);
        if (!isset($s[$i]) || $s[$i] !== ':') {
            return null;
        }
        $i++;

        // Skip whitespace to the first value character
        $i = self::skipWs($s, $i);
        if (!isset($s[$i])) {
            return null;
        }

        $ch = $s[$i];

        if ($ch === '[') {
            return self::scanBalanced($s, $i, '[', ']');
        }

        if ($ch === '{') {
            return self::scanBalanced($s, $i, '{', '}');
        }

        if ($ch === '"') {
            return self::scanJsonString($s, $i);
        }

        // number, true, false, null
        return self::scanPrimitive($s, $i);
    }

    private static function skipWs(string $s, int $i): int
    {
        $len = strlen($s);
        while ($i < $len) {
            $c = $s[$i];
            if ($c !== ' ' && $c !== "\n" && $c !== "\r" && $c !== "\t") {
                break;
            }
            $i++;
        }
        return $i;
    }

    /**
     * Scans from $start (which must be $open) to the matching $close,
     * respecting JSON strings and escapes.
     */
    private static function scanBalanced(
        string $s,
        int $start,
        string $open,
        string $close
    ): ?string {
        $len = strlen($s);
        if (!isset($s[$start]) || $s[$start] !== $open) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;

        for ($i = $start; $i < $len; $i++) {
            $c = $s[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($c === '\\') {
                    $escape = true;
                    continue;
                }
                if ($c === '"') {
                    $inString = false;
                    continue;
                }
                continue;
            }

            if ($c === '"') {
                $inString = true;
                continue;
            }

            if ($c === $open) {
                $depth++;
                continue;
            }

            if ($c === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($s, $start, ($i - $start) + 1);
                }
                continue;
            }
        }

        return null;
    }

    /**
     * Returns the JSON string token starting at a quote, including quotes.
     */
    private static function scanJsonString(string $s, int $start): ?string
    {
        $len = strlen($s);
        if (!isset($s[$start]) || $s[$start] !== '"') {
            return null;
        }

        $escape = false;
        for ($i = $start + 1; $i < $len; $i++) {
            $c = $s[$i];

            if ($escape) {
                $escape = false;
                continue;
            }
            if ($c === '\\') {
                $escape = true;
                continue;
            }
            if ($c === '"') {
                return substr($s, $start, ($i - $start) + 1);
            }
        }

        return null;
    }

    /**
     * Scans a primitive (true/false/null/number) until a JSON delimiter.
     */
    private static function scanPrimitive(string $s, int $start): string
    {
        $len = strlen($s);
        $i = $start;

        while ($i < $len) {
            $c = $s[$i];
            if ($c === ',' || $c === '}' || $c === ']' ||
                $c === "\n" || $c === "\r" || $c === "\t" || $c === ' ') {
                break;
            }
            $i++;
        }

        return substr($s, $start, $i - $start);
    }
}

