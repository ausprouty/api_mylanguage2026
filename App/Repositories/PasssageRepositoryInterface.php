<?php

namespace App\Repositories;

use App\Models\Bible\PassageModel;

/**
 * Contract for repositories that manage Bible passage records.
 *
 * This interface exposes only the operations that callers need, so that
 * different implementations (real DB-backed, null, cached, etc.) can be
 * swapped via DI without changing calling code.
 */
interface PassageRepositoryInterface
{
    /**
     * Check if a Bible passage exists by its ID.
     *
     * @param string $bpid
     *   The composite passage ID (e.g. "1213-Mark-4-35-41").
     *
     * @return bool
     *   TRUE if the passage exists, FALSE otherwise.
     */
    public function existsById(string $bpid): bool;

    /**
     * Find a Bible passage by its ID, using remote lookup if needed.
     *
     * @param string $bpid
     *   The composite passage ID.
     *
     * @return PassageModel|null
     *   A PassageModel if found, or NULL if there is no passage.
     */
    public function findByBpid(string $bpid): ?PassageModel;

    /**
     * Find a Bible passage only if it is already stored locally.
     *
     * @param string $bpid
     *   The composite passage ID.
     *
     * @return PassageModel|null
     *   A PassageModel if stored locally, or NULL otherwise.
     */
    public function findStoredById(string $bpid): ?PassageModel;

    public function savePassageRecord(PassageModel $biblePassage): void;

    public function updatePassageUse(PassageModel $biblePassage): void;
}
