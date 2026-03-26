<?php

declare(strict_types=1);

namespace App\Services\Language;

use App\Contracts\Translation\TranslationService;

final class NullTranslationService implements TranslationService
{
    public function __construct(
        private string $baseLanguage = 'eng00'
    ) {}

    /**
     * @param array<string,mixed> $bundle
     * @param array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    public function translateBundle(
        array $bundle,
        string $languageCodeHL,
        string $variant,
        array $ctx = []
    ): array {
        return $bundle;
    }

    public function baseLanguage(): string
    {
        return $this->baseLanguage;
    }
}
