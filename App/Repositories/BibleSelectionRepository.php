<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Database\DatabaseService;
use Throwable;

/**
 * BibleSelectionRepository
 *
 * Weight semantics (recommended):
 *  9 = locked/manual best (script must not change; skip language)
 *  7 = auto-selected best (script maintains these)
 *  5 = auto-selected NT-only best (script maintains these)
 *  0 = normal/unselected
 */
final class BibleSelectionRepository
{
    public function __construct(
        private DatabaseService $db
    ) {}

    /**
     * Return HL codes that need selection updates:
     * - include every languageCodeHL that exists in `bibles`
     * - EXCEPT those that already have any record with weight=9
     *
     * @return array<int,array{languageCodeHL:string}>
     */
    public function fetchLanguagesNeedingSelection(): array
    {
        $sql = "
            SELECT DISTINCT b.languageCodeHL
            FROM bibles b
            WHERE b.languageCodeHL IS NOT NULL
              AND b.languageCodeHL <> ''
              AND NOT EXISTS (
                  SELECT 1
                  FROM bibles b2
                  WHERE b2.languageCodeHL = b.languageCodeHL
                    AND b2.weight = 9
              )
            ORDER BY b.languageCodeHL
        ";

        /** @var array<int,array{languageCodeHL:string}> $rows */
        $rows = $this->db->fetchAll($sql, []);
        return $rows;
    }

    /**
     * Fetch candidate bibles for a language HL code.
     * We alias DB fields to match what BestBibleSelectionService expects.
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchBiblesForLanguage(string $languageCodeHL): array
    {
        $sql = "
            SELECT
                b.bid AS id,
                b.source AS provider,
                b.format,
                b.collectionCode,
                b.text,
                b.audio,
                b.video,
                b.weight
            FROM bibles b
            WHERE b.languageCodeHL = :hl
              AND (
                    b.text = 1
                    OR LOWER(b.format) LIKE 'text%'
                    OR LOWER(b.format) IN (
                        'json',
                        'text_json',
                        'text_usx',
                        'text_plain',
                        'text_format'
                    )
                  )
        ";

        /** @var array<int,array<string,mixed>> $rows */
        $rows = $this->db->fetchAll($sql, [':hl' => $languageCodeHL]);
        return $rows;
    }

    /**
     * Clear auto-selections for a language. Leaves locked (9) untouched.
     */
    public function clearAutoSelections(string $languageCodeHL): void
    {
        $sql = "
            UPDATE bibles
            SET weight = 0
            WHERE languageCodeHL = :hl
              AND weight IN (7, 5)
        ";

        $this->db->executeOrFail($sql, [':hl' => $languageCodeHL]);
    }

    /**
     * Save a single complete selection (or best single) for the language.
     * This will clear existing auto selections (weight 7 or 5) first.
     */
    public function saveSelectionComplete(
        string $languageCodeHL,
        int $bibleId,
        int $weight
    ): void {
        if ($this->languageHasLockedSelection($languageCodeHL)) {
            return;
        }

        $this->db->beginTransaction();
        try {
            $this->clearAutoSelections($languageCodeHL);

            $sql = "
                UPDATE bibles
                SET weight = :w
                WHERE languageCodeHL = :hl
                  AND bid = :id
            ";

            $this->db->executeOrFail($sql, [
                ':w'  => $weight,
                ':hl' => $languageCodeHL,
                ':id' => $bibleId,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Save an OT/NT pair selection for the language.
     * This will clear existing auto selections (weight 7) first.
     */
    public function saveSelectionPair(
        string $languageCodeHL,
        int $otBibleId,
        int $ntBibleId,
        int $weight
    ): void {
        if ($this->languageHasLockedSelection($languageCodeHL)) {
            return;
        }

        $this->db->beginTransaction();
        try {
            $this->clearAutoSelections($languageCodeHL);

            // Use two updates to avoid "IN (:a, :b)" placeholder issues
            // in some DB wrappers.
            $sql = "
                UPDATE bibles
                SET weight = :w
                WHERE languageCodeHL = :hl
                  AND bid = :id
            ";

            $paramsBase = [
                ':w'  => $weight,
                ':hl' => $languageCodeHL,
            ];

            $this->db->executeOrFail($sql, $paramsBase + [':id' => $otBibleId]);
            $this->db->executeOrFail($sql, $paramsBase + [':id' => $ntBibleId]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function languageHasLockedSelection(string $languageCodeHL): bool
    {
        $sql = "
            SELECT 1
            FROM bibles
            WHERE languageCodeHL = :hl
              AND weight = 9
            LIMIT 1
        ";

        $row = $this->db->fetchOne($sql, [':hl' => $languageCodeHL]);
        return (bool) $row;
    }
}
