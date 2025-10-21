<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;
use RuntimeException;

final class I18nResourcesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );
        $this->pdo->setAttribute(
            PDO::ATTR_EMULATE_PREPARES,
            false
        );
    }

    /**
     * Prepare + execute with diagnostics for HY093 mismatches.
     *
     * @param array<string,scalar|null> $params
     */
    private function prepareAndExecute(
        string $sql,
        array $params
    ): PDOStatement {
        // Extract :placeholders from SQL
        preg_match_all(
            '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
            $sql,
            $m
        );
        $placeholders = array_values(
            array_unique($m[1] ?? [])
        );

        // Compare sets
        $boundKeys = array_keys($params);
        $missing = array_values(
            array_diff($placeholders, $boundKeys)
        );
        $extra = array_values(
            array_diff($boundKeys, $placeholders)
        );

        if ($missing || $extra) {
            $sqlOne = preg_replace('/\s+/', ' ', trim($sql));
            throw new RuntimeException(
                'SQL parameter mismatch. '
                . ($missing
                    ? 'Missing: ' . implode(', ', $missing) . '. '
                    : '')
                . ($extra
                    ? 'Extra: ' . implode(', ', $extra) . '. '
                    : '')
                . 'SQL: ' . mb_substr($sqlOne, 0, 240)
            );
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Find resource id by (type, subject, variant).
     * Variant compares NULL-safely to allow default rows.
     */
    public function getIdByTypeSubjectVariant(
        string $resourceType,
        string $resourceSubject,
        ?string $resourceVariant
    ): ?int {
        $sql = 'SELECT `resourceId` '
            . 'FROM `i18n_resources` '
            . 'WHERE `type` = :type '
            . 'AND `subject` = :subject '
            . 'AND (`variant` <=> :variant) '
            . 'LIMIT 1';

        $params = [
            'type'    => $resourceType,
            'subject' => $resourceSubject,
            'variant' => $resourceVariant,
        ];

        $stmt = $this->prepareAndExecute($sql, $params);
        $val = $stmt->fetchColumn();

        return $val !== false ? (int) $val : null;
    }

    /**
     * Ensure a row exists for (type, subject, variant); return its id.
     * Uses ON DUPLICATE KEY + LAST_INSERT_ID for race-safe id return.
     */
    public function ensureIdByTypeSubjectVariant(
        string $resourceType,
        string $resourceSubject,
        ?string $resourceVariant,
        ?string $description = null
    ): int {
        $id = $this->getIdByTypeSubjectVariant(
            $resourceType,
            $resourceSubject,
            $resourceVariant
        );
        if ($id !== null) {
            return $id;
        }

        $sql = 'INSERT INTO `i18n_resources` '
            . '(`type`, `subject`, `variant`, `description`) '
            . 'VALUES '
            . '(:type_ins, :subject_ins, :variant_ins, :desc_ins) '
            . 'ON DUPLICATE KEY UPDATE '
            . '`resourceId` = LAST_INSERT_ID(`resourceId`)';

        $params = [
            'type_ins'    => $resourceType,
            'subject_ins' => $resourceSubject,
            'variant_ins' => $resourceVariant,
            'desc_ins'    => $description ?? sprintf(
                '%s %s %s (auto-created)',
                $resourceType,
                $resourceSubject,
                $resourceVariant ?? 'default'
            ),
        ];

        $this->prepareAndExecute($sql, $params);

        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            return $id;
        }

        // Paranoid fallback (rare)
        $id = $this->getIdByTypeSubjectVariant(
            $resourceType,
            $resourceSubject,
            $resourceVariant
        );
        if ($id !== null) {
            return $id;
        }

        throw new RuntimeException(
            'Failed to ensure i18n_resources row.'
        );
    }

    /**
     * Optional helpers
     */
    public function getRowById(int $resourceId): ?array
    {
        $sql = 'SELECT `resourceId`, `type`, `subject`, `variant`, '
            . '`description`, `createdAt`, `updatedAt` '
            . 'FROM `i18n_resources` '
            . 'WHERE `resourceId` = :id '
            . 'LIMIT 1';

        $stmt = $this->prepareAndExecute($sql, ['id' => $resourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function upsertDescription(
        int $resourceId,
        string $description
    ): void {
        $sql = 'UPDATE `i18n_resources` '
            . 'SET `description` = :desc '
            . 'WHERE `resourceId` = :id';

        $this->prepareAndExecute($sql, [
            'desc' => $description,
            'id'   => $resourceId,
        ]);
    }
}
