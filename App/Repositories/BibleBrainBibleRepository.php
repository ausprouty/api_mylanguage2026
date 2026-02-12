<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use InvalidArgumentException;

/**
 * BibleBrainBibleRepository
 *
 * Handles BibleBrain-related synchronization between BibleBrain API data
 * and local `bibles` (and related language) tables.
 */
class BibleBrainBibleRepository extends BaseRepository
{
    /**
     * Only save records that are full Bible, NT-only, or OT-only.
     * Everything else (portions/partials/combined codes) is skipped.
     */
    private const ALLOWED_COLLECTION_CODES = ['C', 'NT', 'OT'];

    private const BOOL_INT_FIELDS = ['text', 'audio', 'video', 'bibleBrainReviewed'];


    
     public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    /**
     * Updates the externalId for a Bible row and stamps dateVerified = today.
     */
    public function updateExternalId(int $bid, string $newId): void
    {
        $query = '
            UPDATE bibles
               SET externalId = :externalId,
                   dateVerified = CURDATE()
             WHERE bid = :bid
        ';
        $this->databaseService->executeQuery($query, [
            ':externalId' => $newId,
            ':bid'        => $bid,
        ]);
    }

    /**
     * Updates the dateVerified field to today's date for a Bible row.
     */
    public function updateDateVerified(int $bid): void
    {
        $query = '
            UPDATE bibles
               SET dateVerified = CURDATE()
             WHERE bid = :bid
        ';
        $this->databaseService->executeQuery($query, [':bid' => $bid]);
    }

    /**
     * Checks if a Bible record already exists by externalId.
     */
    public function bibleRecordExists(string $externalId): bool
    {
        $query = '
            SELECT bid
              FROM bibles
             WHERE externalId = :externalId
             LIMIT 1
        ';
        return $this->databaseService->fetchSingleValue($query, [
            ':externalId' => $externalId,
        ]) !== null;
    }

    /**
     * Inserts a new Bible record into the `bibles` table.
     *
     * @param array<string,mixed> $data  Column => value (camelCase column names)
     */
    public function insertBibleRecord(array $data): void
    {
        if (empty($data)) {
            return;
        }
        // Hard guard: skip anything not exactly C/NT/OT.
        $ccRaw = $data['collectionCode'] ?? null;
        $cc = is_string($ccRaw) ? strtoupper(trim($ccRaw)) : '';
        if ($cc === '' || !in_array($cc, self::ALLOWED_COLLECTION_CODES, true)) {
            LoggerService::logWarning('BibleBrainBibleRepository-skipInsert', [
                'reason'         => 'collectionCode not allowed',
                'collectionCode' => $ccRaw,
                'externalId'     => $data['externalId'] ?? null,
                'format'         => $data['format'] ?? null,
                'volumeName'     => $data['volumeName'] ?? null,
                'languageIso'    => $data['languageCodeIso'] ?? null,
            ]);
            return;
        }
        $data['collectionCode'] = $cc;

        $data = $this->normalizeInsertData($data);
        if (!array_key_exists('dateVerified', $data) || $data['dateVerified'] === null) {
            $data['dateVerified'] = date('Y-m-d');
        }

        $columns      = array_keys($data);
        $placeholders = array_map(fn ($col) => ':' . $col, $columns);

        $query = sprintf(
            'INSERT INTO bibles (%s) VALUES (%s)',
            implode(',', $columns),
            implode(',', $placeholders)
        );

        $params = array_combine($placeholders, array_values($data));
        $this->databaseService->executeQuery($query, $params);
    }

    /**
     * Finds an existing Bible record by language & volume name (DBT source).
     */
    public function findMatchingBible(string $languageCodeIso, string $volumeName, string $format = 'text'): ?array
    {
        $query = '
            SELECT *
              FROM bibles
             WHERE languageCodeIso = :iso
               AND format = :format
               AND source = \'dbt\'
               AND volumeName LIKE CONCAT(\'%\', :volumeName, \'%\')
             LIMIT 1
        ';

        return $this->databaseService->fetchRow($query, [
            ':iso'        => $languageCodeIso,
            ':format'     => $format,
            ':volumeName' => $volumeName,
        ]);
    }

    /**
     * Retrieves a batch of bibles for initial cleanup.
     * Only includes Bibles that have not yet been verified.
     */
    public function getBiblesForCleanup(int $limit, int $lastBid = 0): array
    {
        $query = '
            SELECT *
              FROM bibles
             WHERE source = :source
               AND format LIKE :formatPrefix
               AND (dateVerified IS NULL OR dateVerified = "0000-00-00")
               AND bid > :lastBid
             ORDER BY bid ASC
             LIMIT :limit
        ';

        $params = [
            ':source'       => 'dbt',
            ':formatPrefix' => 'text%',
            ':lastBid'      => $lastBid,
            ':limit'        => $limit,
        ];

        return $this->databaseService->fetchAll($query, $params);
    }

    /**
     * Fills language-related fields if missing for a given externalId.
     * (Keeps your camelCase, stamps dateVerified.)
     */
    public function updateLanguageFieldsIfMissing(string $externalId, array $entry): void
    {
        $query = '
            UPDATE bibles
               SET languageEnglish        = :languageEnglish,
                   languageName           = :languageAutonym,
                   languageCodeBibleBrain = :languageCodeBibleBrain,
               WHERE externalId = :externalId
        ';

        $this->databaseService->executeQuery($query, [
            ':languageEnglish'       => $entry['language']     ?? '',
            ':languageAutonym'       => $entry['autonym']      ?? '',
            ':languageCodeBibleBrain'=> $entry['language_id']  ?? '',
            ':externalId'            => $externalId,
        ]);
    }

    /**
     * Atomically set/confirm BibleBrain externalId and verification timestamp.
     * Returns true when the row reflects the given externalId and verified date.
     *
     * Notes:
     * - Uses camelCase fields: externalId, dateVerified.
     * - If you prefer to *avoid* overwriting an existing different externalId,
     *   set $strict = true (see guard below).
     */
    public function updateExternalIdAndVerified(
        int $bibleId,
        string $externalId,
        \DateTimeInterface $when
    ): bool {
        $date = $when->format('Y-m-d'); // dateVerified appears to be DATE

        // Optional strict guard: refuse to overwrite a different externalId.
        // Uncomment if you want this safety.
        /*
        $existing = $this->databaseService->fetchSingleValue(
            'SELECT externalId FROM bibles WHERE bid = :bid LIMIT 1',
            [':bid' => $bibleId]
        );
        if ($existing !== null && $existing !== '' && $existing !== $externalId) {
            // conflict: do not overwrite silently
            return false;
        }
        */

        $update = '
            UPDATE bibles
               SET externalId   = :externalId,
                   dateVerified = :dateVerified
             WHERE bid = :bid
        ';
        $this->databaseService->executeQuery($update, [
            ':externalId'  => $externalId,
            ':dateVerified'=> $date,
            ':bid'         => $bibleId,
        ]);

        // Verify persisted state
        $check = '
            SELECT 1
              FROM bibles
             WHERE bid = :bid
               AND externalId = :externalId
               AND dateVerified = :dateVerified
             LIMIT 1
        ';
        return $this->databaseService->fetchSingleValue($check, [
            ':bid'         => $bibleId,
            ':externalId'  => $externalId,
            ':dateVerified'=> $date,
        ]) !== null;
    }

    public function markVerifiedByExternalId(string $externalId): void
    {
        $query = '
            UPDATE bibles
            SET dateVerified = CURDATE()
            WHERE externalId = :externalId
        ';
        $this->databaseService->executeQuery($query, [
            ':externalId' => $externalId,
        ]);
    }


    /**
     * Guard + normalize insert data.
     *
     * - Ensures column names are safe identifiers.
     * - Converts Y/N and common truthy/falsey values to 1/0 for INT-bool fields.
     * - Converts '' to 0 for INT-bool fields (avoids MySQL strict errors).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalizeInsertData(array $data): array
    {
        foreach ($data as $col => $val) {
            if (!is_string($col) || $col === '') {
                throw new InvalidArgumentException(
                    'Insert data contains an invalid column key.'
                );
            }

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $col)) {
                throw new InvalidArgumentException(
                    'Unsafe column name: ' . $col
                );
            }

            if (in_array($col, self::BOOL_INT_FIELDS, true)) {
                $data[$col] = $this->toIntBool($col, $val);
            }
        }

        return $data;
    }

    /**
     * Convert common boolean-like values to 1/0 for INT columns.
     *
     * @param mixed $val
     */
    private function toIntBool(string $field, $val): int
    {
        if ($val === null || $val === '') {
            return 0;
        }

        if (is_bool($val)) {
            return $val ? 1 : 0;
        }

        if (is_int($val)) {
            return $val === 0 ? 0 : 1;
        }

        if (is_string($val)) {
            $v = strtoupper(trim($val));
            if ($v === 'Y' || $v === 'YES' || $v === 'TRUE' || $v === 'T') {
                return 1;
            }
            if ($v === 'N' || $v === 'NO' || $v === 'FALSE' || $v === 'F') {
                return 0;
            }
            if ($v === '1' || $v === '0') {
                return (int) $v;
            }
        }

        throw new InvalidArgumentException(
            'Invalid value for ' . $field . ': ' . print_r($val, true)
        );
    }


    
}
