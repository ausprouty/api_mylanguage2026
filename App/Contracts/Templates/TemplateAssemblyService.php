<?php
declare(strict_types=1);

namespace App\Contracts\Templates;

/**
 * Base bundle assembler (untranslated).
 */
interface TemplateAssemblyService
{
    /**
     * @return array<string,mixed>
     */
    public function get(string $kind, string $subject): array;

    /**
     * Monotonic content version for cache/ETag.
     */
    public function version(string $kind, string $subject): string;
}
