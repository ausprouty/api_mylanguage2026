<?php

declare(strict_types=1);

namespace App\Services\Numerals;

use App\Repository\NumeralSystemRepository;

final class NumeralSystemConverter
{
    private const FALLBACK_CODE = 'latn';

    public function __construct(
        private NumeralSystemRepository $repo
    ) {
    }

    /**
     * Convert Latin digits and separators in a reference
     * into the digits defined by the numeral system.
     *
     * If the numeral system is 'latn' or blank:
     *   → no conversion needed, return as-is.
     */
    public function convertReference(
        string $reference,
        string $numeralSetCode
    ): string {
        $code = trim($numeralSetCode);

        // ----------------------------------------------------
        // Fast path: Latin digits need zero conversion.
        // ----------------------------------------------------
        if ($code === '' || $code === self::FALLBACK_CODE) {
            return $reference;
        }

        $system = $this->repo->findOneByCode($code);

        // If unknown code or broken DB row, fall back to latn (no change)
        if ($system === null) {
            return $reference;
        }

        $map = $this->buildMap($system);

        return strtr($reference, $map);
    }

    /**
     * Build strtr() translation map for digits + separators.
     *
     * @param array<string,string> $system
     * @return array<string,string>
     */
    private function buildMap(array $system): array
    {
        $map = [];

        // Digits 0–9
        for ($i = 0; $i <= 9; $i++) {
            $latin = (string) $i;
            $key   = "digit{$i}";

            // If DB is missing a field, fall back gracefully
            $map[$latin] = $system[$key] ?? $latin;
        }

        // Separators — use DB values or defaults
        $chapter = $system['chapter_sep'] ?? ':';
        $range   = $system['range_sep']   ?? '–';

        // Map all possible forms in incoming references
        $map[':'] = $chapter;
        $map['-'] = $range;
        $map['–'] = $range;

        return $map;
    }
}
