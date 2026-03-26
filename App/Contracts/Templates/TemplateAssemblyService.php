<?php

declare(strict_types=1);

namespace App\Contracts\Templates;

/**
 * Base bundle assembler (untranslated).
 */
interface TemplateAssemblyService
{
    public function get(
        string $kind,
        string $subject,
        string $variant
    ): array;

    public function version(
        string $kind,
        string $subject,
        string $variant
    ): string;
}
