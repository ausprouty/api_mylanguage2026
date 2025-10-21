<?php
declare(strict_types=1);

namespace App\Contracts\Translation;

/**
 * Translates a base bundle to a target language/variant.
 */
interface TranslationService
{
    /**
     * @param array<string,mixed> $bundle
     * @return array<string,mixed>
     */
    public function translateBundle(
        array $bundle,
        string $languageCodeHL,
        ?string $variant,
        array $ctx = []
    ): array;

    public function baseLanguage(): string; // e.g., "eng00"
}
