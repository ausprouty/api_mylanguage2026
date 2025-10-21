<?php
declare(strict_types=1);

namespace App\Contracts\Translation;

/**
 * Batch machine translation provider.
 *
 * Implementations must:
 *  - keep output order identical to input order
 *  - return exactly one translated string per input element
 *  - throw on transport/auth/quota errors (don’t silently partial-return)
 *  - respect $format when the backend supports it ('text'|'html')
 *    • 'text' : translate as plain text
 *    • 'html' : preserve tags/attributes; translate only text content
 *
 * Notes:
 *  - $texts may be chunked internally to satisfy provider size limits.
 *  - If the backend doesn't support 'html', the implementation should either:
 *      a) fall back to 'text' safely, or
 *      b) throw a descriptive exception.
 *  - Placeholders (e.g. {{name}}, %s) should be protected if possible to avoid
 *    being altered by the MT engine (implementation detail).
 */
interface TranslationProvider
{
    public const FORMAT_TEXT = 'text';
    public const FORMAT_HTML = 'html';

    /**
     * Translate a batch of strings from $sourceLanguage to $targetLanguage.
     *
     * @param array<int,string> $texts          Non-empty list of strings to translate.
     * @param string            $targetLanguage BCP-47 / ISO code (e.g. 'fr', 'pt-BR').
     * @param string            $sourceLanguage Source language code; defaults to 'en'.
     * @param 'text'|'html'     $format         Input format; defaults to 'text'.
     *
     * @return array<int,string>                Translations, same length/order as $texts.
     *
     * @throws \InvalidArgumentException        If inputs are invalid (e.g., empty array).
     * @throws \RuntimeException|\Exception     On backend/transport/auth/quota errors.
     */
    public function translate(
        array $texts,
        string $targetLanguage,
        string $sourceLanguage = 'en',
        string $format = self::FORMAT_TEXT
    ): array;
}
