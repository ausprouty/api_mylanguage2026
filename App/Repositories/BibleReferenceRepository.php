<?php
declare(strict_types=1);

namespace App\Repositories;

/**
 * Thin adapter used by controllers that type-hint BibleReferenceRepository.
 * It wraps (composes) the real PassageReferenceRepository to avoid DI cycles.
 */
final class BibleReferenceRepository
{
    public function __construct(private PassageReferenceRepository $inner) {}

    // Optionally proxy methods here as you need, e.g.:
    // public function findByRef(...) { return $this->inner->findByRef(...); }
}
