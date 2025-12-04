<?php



// Maintenance/GoogleLanguages/MapLanguageScripts.php

// 1) Simple helper: normalise google code
function normalizeGoogleCode(?string $code): ?string
{
    if (!$code) {
        return null;
    }

    $code = trim(strtolower($code));
    if ($code === '') {
        return null;
    }

    // Take only the language part: "pt-br" -> "pt"
    $parts = explode('-', $code, 2);
    return $parts[0];
}

// 2) Mapping: Google language code (normalised) -> [script, numeralSet]
//   Uses ISO 15924 script codes (Latn, Arab, Deva, …)
//   and CLDR-style numeral IDs (latn, arab, deva, …)
function getScriptAndNumeralsForGoogle(?string $googleCode): array
{
    $lang = normalizeGoogleCode($googleCode);

    // Default: Latin script + Latin digits
    $default = ['script' => 'Latn', 'numeralSet' => 'latn'];

    if (!$lang) {
        return $default;
    }

    static $map = [
        // Arabic-script languages
        'ar' => ['script' => 'Arab', 'numeralSet' => 'arab'],
        'fa' => ['script' => 'Arab', 'numeralSet' => 'arabext'], // Persian often uses extended digits
        'ur' => ['script' => 'Arab', 'numeralSet' => 'arabext'],
        'ps' => ['script' => 'Arab', 'numeralSet' => 'arab'],
        'sd' => ['script' => 'Arab', 'numeralSet' => 'arab'],
        'ku' => ['script' => 'Arab', 'numeralSet' => 'arab'], // Sorani

        // Hebrew
        'he' => ['script' => 'Hebr', 'numeralSet' => 'latn'], // usually Latin digits online

        // Devanagari (India/Nepal)
        'hi' => ['script' => 'Deva', 'numeralSet' => 'deva'],
        'mr' => ['script' => 'Deva', 'numeralSet' => 'deva'],
        'ne' => ['script' => 'Deva', 'numeralSet' => 'deva'],

        // Bengali
        'bn' => ['script' => 'Beng', 'numeralSet' => 'beng'],

        // Gurmukhi (Punjabi)
        'pa' => ['script' => 'Guru', 'numeralSet' => 'guru'],

        // Gujarati
        'gu' => ['script' => 'Gujr', 'numeralSet' => 'gujr'],

        // Odia
        'or' => ['script' => 'Orya', 'numeralSet' => 'orya'],

        // South Indian scripts
        'ta' => ['script' => 'Taml', 'numeralSet' => 'tamldec'],
        'te' => ['script' => 'Telu', 'numeralSet' => 'telu'],
        'kn' => ['script' => 'Knda', 'numeralSet' => 'knda'],
        'ml' => ['script' => 'Mlym', 'numeralSet' => 'mlym'],

        // Southeast Asian scripts
        'th' => ['script' => 'Thai', 'numeralSet' => 'thai'],
        'lo' => ['script' => 'Laoo', 'numeralSet' => 'laoo'],
        'km' => ['script' => 'Khmr', 'numeralSet' => 'khmr'],
        'my' => ['script' => 'Mymr', 'numeralSet' => 'mymr'],
        'si' => ['script' => 'Sinh', 'numeralSet' => 'latn'], // Sinhala often uses Latin digits online

        // East Asia
        'zh' => ['script' => 'Hant', 'numeralSet' => 'latn'], // Chinese; usually Latin digits in modern text
        'ja' => ['script' => 'Jpan', 'numeralSet' => 'latn'], // Japanese; uses 0–9 in most digital contexts
        'ko' => ['script' => 'Kore', 'numeralSet' => 'latn'],

        // Everything else here: Latin script
        'en' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'fr' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'de' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'es' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'pt' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'it' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'nl' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'sv' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'no' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'da' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'fi' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'pl' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'cs' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'sk' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'hu' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'ro' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'tr' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'id' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'ms' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'sw' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'vi' => ['script' => 'Latn', 'numeralSet' => 'latn'],
        'ru' => ['script' => 'Cyrl', 'numeralSet' => 'latn'], // Cyrillic but digits are 0–9
        'uk' => ['script' => 'Cyrl', 'numeralSet' => 'latn'],
        'bg' => ['script' => 'Cyrl', 'numeralSet' => 'latn'],
        'sr' => ['script' => 'Cyrl', 'numeralSet' => 'latn'], // often mixed, keep latn digits
        'el' => ['script' => 'Grek', 'numeralSet' => 'latn'],
    ];

    return $map[$lang] ?? $default;
}
