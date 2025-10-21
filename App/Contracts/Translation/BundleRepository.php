<?php

namespace App\Contracts\Translation;

interface BundleRepository
{
    /**
     * Return the master bundle (defaults to eng00) for a type + sourceKey.
     * - For type='interface', sourceKey is the site/app key (e.g., 'wsu').
     * - For other types (e.g., 'commonContent'), sourceKey is the study key.
     */
    public function getMaster(
        string $type,
        string $sourceKey,
        string $lang = 'eng00',
        ?string $variant = null
    ): array;
}
