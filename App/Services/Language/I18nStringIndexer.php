<?php
declare(strict_types=1);

namespace App\Services\Language;

use App\Services\Database\DatabaseService;

/**
 * I18nStringIndexer
 *
 * Index (or re-index) master English strings for a resource into i18n_strings.
 * You provide the already-loaded English bundle (eng00) as an array.
 *
 * Tables (camelCase):
 *  - i18n_resources(resourceId, type, subject, variant)
 *  - i18n_strings(stringId, resourceId, stringKey, keyHash, englishText, isActive, createdAt, updatedAt)
 */
final class I18nStringIndexer
{
    public function __construct(private DatabaseService $db) {}

    /**
     * Index (or re-index) master English strings for a resource.
     *
     * @param string      $type               'interface' | 'commonContent' | ...
     * @param string      $subject            e.g. 'app', 'hope'
     * @param string|null $variant            e.g. 'wsu' or null
     * @param array       $bundle             The *English* bundle (eng00) already loaded
     * @param bool        $deactivateMissing  If true, sets isActive=0 for strings not seen this run
     * @param array       $skipTopKeys        Top-level keys to skip entirely (default: ['language','meta'])
     * @return int        $resourceId
     */
    public function indexFromBundle(
        string $type,
        string $subject,
        ?string $variant,
        array $bundle,
        bool $deactivateMissing = true,
        array $skipTopKeys = ['language', 'meta']
    ): int {
        // 1) Resolve or create the resource row
        $resourceId = $this->resolveOrCreateResource($type, $subject, $variant);

        // 2) Flatten the bundle to leaf strings
        [$uniqueTexts, $paths] = $this->flattenTranslatables($bundle, $skipTopKeys);

        if (empty($paths)) {
            return $resourceId; // nothing to index
        }

        // 3) Upsert each string by (resourceId + keyHash)
        $seenHashes = [];
        foreach ($paths as $row) {
            $path = $row['path'];
            $text = $row['text'];
            $hash = $this->sha1Text($text);
            $seenHashes[$hash] = true;

            $this->upsertString(
                resourceId: $resourceId,
                stringKey:  implode('.', $path),
                keyHash:    $hash,
                english:    $text
            );
        }

        // 4) Optionally deactivate strings no longer present
        if ($deactivateMissing) {
            $this->deactivateMissing($resourceId, array_keys($seenHashes));
        }

        return $resourceId;
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    private function resolveOrCreateResource(string $type, string $subject, ?string $variant): int
    {
        $rid = $this->db->fetchValue(
            'SELECT resourceId
               FROM i18n_resources
              WHERE type = :type
                AND subject = :subject
                AND (variant <=> :variant)
              LIMIT 1',
            [
                ':type'    => $type,
                ':subject' => $subject,
                ':variant' => ($variant === null || $variant === '') ? null : $variant,
            ]
        );

        if ($rid) {
            return (int)$rid;
        }

        $this->db->executeQuery(
            'INSERT INTO i18n_resources (type, subject, variant)
             VALUES (:type, :subject, :variant)',
            [
                ':type'    => $type,
                ':subject' => $subject,
                ':variant' => ($variant === null || $variant === '') ? null : $variant,
            ]
        );

        $newId = $this->db->fetchValue('SELECT LAST_INSERT_ID()');
        return (int)$newId;
    }

    /**
     * Flatten human-facing strings.
     *
     * @return array{0: array<string>, 1: array<int, array{path: array<int,string>, text: string}>}
     */
    private function flattenTranslatables(array $bundle, array $skipTopKeys): array
    {
        $paths  = [];
        $unique = [];

        $walk = function ($node, array $path) use (&$walk, &$paths, &$unique, $skipTopKeys): void {
            if (empty($path) && is_array($node)) {
                // remove top-level blocks completely
                foreach ($skipTopKeys as $skip) {
                    unset($node[$skip]);
                }
            }
            if (is_array($node)) {
                foreach ($node as $k => $v) {
                    $p = [...$path, (string)$k];
                    if (is_array($v)) {
                        $walk($v, $p);
                    } elseif (is_string($v)) {
                        if ($this->looksHumanText($p, $v)) {
                            $paths[] = ['path' => $p, 'text' => $v];
                            $unique[$v] = true;
                        }
                    }
                }
            }
        };

        $walk($bundle, []);
        return [array_keys($unique), $paths];
    }

    private function looksHumanText(array $path, string $text): bool
    {
        if ($text === '' || trim($text) === '') return false;
        $last = strtolower((string)end($path));
        if (str_contains($last, 'code') || str_contains($last, 'id')) return false;
        return true;
    }

    private function upsertString(int $resourceId, string $stringKey, string $keyHash, string $english): void
    {
        // Try update first (common case)
        $update = '
            UPDATE i18n_strings
               SET englishText = :englishText,
                   isActive    = 1,
                   updatedAt   = CURRENT_TIMESTAMP
             WHERE resourceId = :resourceId
               AND keyHash    = :keyHash
        ';
        $count = $this->db->executeQuery($update, [
            ':englishText' => $english,
            ':resourceId'  => $resourceId,
            ':keyHash'     => $keyHash,
        ]);

        if ($count > 0) {
            return;
        }

        // Insert if not present
        $insert = '
            INSERT INTO i18n_strings
                (resourceId, stringKey, keyHash, englishText, isActive, createdAt, updatedAt)
            VALUES
                (:resourceId, :stringKey, :keyHash, :englishText, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ';
        $this->db->executeQuery($insert, [
            ':resourceId'  => $resourceId,
            ':stringKey'   => $stringKey,
            ':keyHash'     => $keyHash,
            ':englishText' => $english,
        ]);
    }

    private function deactivateMissing(int $resourceId, array $presentHashes): void
    {
        if (empty($presentHashes)) {
            // If nothing present, deactivate all for this resource
            $this->db->executeQuery(
                'UPDATE i18n_strings
                    SET isActive = 0, updatedAt = CURRENT_TIMESTAMP
                  WHERE resourceId = :rid',
                [':rid' => $resourceId]
            );
            return;
        }

        // Build IN clause safely
        $params = [':rid' => $resourceId];
        $phs    = [];
        foreach ($presentHashes as $i => $hash) {
            $ph = ':h' . $i;
            $phs[] = $ph;
            $params[$ph] = $hash;
        }

        $sql = '
            UPDATE i18n_strings
               SET isActive = 0,
                   updatedAt = CURRENT_TIMESTAMP
             WHERE resourceId = :rid
               AND keyHash NOT IN (' . implode(',', $phs) . ')
        ';

        $this->db->executeQuery($sql, $params);
    }

    private function sha1Text(string $s): string
    {
        return sha1($s);
    }
}
