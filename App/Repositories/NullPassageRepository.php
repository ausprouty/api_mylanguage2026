<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Bible\PassageModel;

/**
 * Null-object implementation of PassageRepositoryInterface.
 *
 * This can be used in environments where Bible passage lookups are disabled
 * or unavailable. All reads return NULL / FALSE and writes are no-ops.
 */
final class NullPassageRepository implements PassageRepositoryInterface
{
    public function existsById(string $bpid): bool
    {
        return false;
    }

    public function findByBpid(string $bpid): ?PassageModel
    {
        return null;
    }

    public function findStoredById(string $bpid): ?PassageModel
    {
        return null;
    }

    public function savePassageRecord(
        PassageModel $biblePassage
    ): void {
        // Intentionally do nothing.
    }

    public function updatePassageUse(
        PassageModel $biblePassage
    ): void {
        // Intentionally do nothing.
    }
}
