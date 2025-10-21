<?php
declare(strict_types=1);

namespace App\Contracts\Translation;

interface ProviderSelector
{
    /**
     * Returns a short key like 'google' or 'null'.
     */
    public function chosenKey(): string;

    /**
     * Returns the FQCN of the chosen provider that implements
     * TranslationProvider.
     */
    public function chosenClass(): string;
}
