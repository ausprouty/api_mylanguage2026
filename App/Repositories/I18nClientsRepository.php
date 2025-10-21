<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class I18nClientsRepository
{
    private PDO $pdo;
    /** @var array<string,bool> */
    private array $columnCache = [];
    private string $dbName;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->dbName = (string) $this->pdo
            ->query('SELECT DATABASE()')
            ->fetchColumn();
    }

    /**
     * Return clientId by clientCode (and variant if present in schema/arg).
     */
    public function getIdByCode(
        string $clientCode,
        ?string $variant = null
    ): ?int {
        if ($this->hasColumn('variant')) {
            $sql = 'SELECT `clientId`
                      FROM `i18n_clients`
                     WHERE `clientCode` = :code
                       AND (`variant` <=> :variant)
                     LIMIT 1';
            $st = $this->pdo->prepare($sql);
            $st->execute([
                'code'    => $clientCode,
                'variant' => $variant,
            ]);
        } else {
            $sql = 'SELECT `clientId`
                      FROM `i18n_clients`
                     WHERE `clientCode` = :code
                     LIMIT 1';
            $st = $this->pdo->prepare($sql);
            $st->execute(['code' => $clientCode]);
        }

        $id = $st->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /**
     * Ensure a client row exists; return its id.
     * Works whether your table has variant/clientName/isActive columns or not.
     *
     * @param ?string $variant     NULL targets the default "slot" if column exists
     * @param ?string $clientName  Optional display name
     * @param ?int    $isActive    Optional active flag (1/0)
     */
    public function ensureIdByCode(
        string $clientCode,
        ?string $variant = null,
        ?string $clientName = null,
        ?int $isActive = null
    ): int {
        $id = $this->getIdByCode($clientCode, $variant);
        if ($id !== null) {
            return $id;
        }

        // Build insert dynamically to match your actual schema.
        $cols = ['clientCode'];
        $vals = [':code'];
        $params = ['code' => $clientCode];

        if ($this->hasColumn('variant')) {
            $cols[] = 'variant';
            $vals[] = ':variant';
            $params['variant'] = $variant;
        }

        if ($this->hasColumn('clientName') && $clientName !== null) {
            $cols[] = 'clientName';
            $vals[] = ':name';
            $params['name'] = $clientName;
        }

        if ($this->hasColumn('isActive') && $isActive !== null) {
            $cols[] = 'isActive';
            $vals[] = ':active';
            $params['active'] = $isActive;
        }

        $setTimestamps = $this->hasColumn('createdAt') && $this->hasColumn('updatedAt');
        if ($setTimestamps) {
            $cols[] = 'createdAt';
            $cols[] = 'updatedAt';
            $vals[] = 'UTC_TIMESTAMP()';
            $vals[] = 'UTC_TIMESTAMP()';
        }

        $insert = sprintf(
            'INSERT INTO `i18n_clients` (%s) VALUES (%s)
             ON DUPLICATE KEY UPDATE `clientId` = LAST_INSERT_ID(`clientId`)%s',
            implode(',', array_map(fn($c) => "`$c`", $cols)),
            implode(',', $vals),
            $this->buildOnDupUpdate($clientName, $isActive, $setTimestamps)
        );

        $st = $this->pdo->prepare($insert);
        $st->execute($params);

        $newId = (int) $this->pdo->lastInsertId();
        if ($newId > 0) {
            return $newId;
        }

        // Paranoid fallback
        $id = $this->getIdByCode($clientCode, $variant);
        if ($id !== null) {
            return $id;
        }

        throw new RuntimeException('Failed to ensure i18n_clients row.');
    }

    /**
     * Cache-aware column existence check.
     */
    private function hasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }
        $sql = 'SELECT 1
                  FROM `information_schema`.`COLUMNS`
                 WHERE `TABLE_SCHEMA` = :db
                   AND `TABLE_NAME` = "i18n_clients"
                   AND `COLUMN_NAME` = :col
                 LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute(['db' => $this->dbName, 'col' => $column]);
        $exists = $st->fetchColumn() !== false;
        $this->columnCache[$column] = $exists;
        return $exists;
    }

    /**
     * Build the ON DUPLICATE KEY UPDATE clause to keep metadata fresh.
     */
    private function buildOnDupUpdate(
        ?string $clientName,
        ?int $isActive,
        bool $setTimestamps
    ): string {
        $updates = [];
        if ($clientName !== null && $this->hasColumn('clientName')) {
            $updates[] = '`clientName` = VALUES(`clientName`)';
        }
        if ($isActive !== null && $this->hasColumn('isActive')) {
            $updates[] = '`isActive` = VALUES(`isActive`)';
        }
        if ($setTimestamps) {
            $updates[] = '`updatedAt` = UTC_TIMESTAMP()';
        }
        return $updates ? ', ' . implode(', ', $updates) : '';
    }
}
