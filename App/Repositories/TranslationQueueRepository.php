<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Database\DatabaseService;
use App\Services\LoggerService;

final class TranslationQueueRepository
{
    public function __construct(private DatabaseService $db) {}

    /**
     * Enqueue a job (idempotent via UNIQUE constraints).
     * - Preferred uniqueness: (sourceStringId, targetLanguageCodeGoogle)
     * - Fallback uniqueness: (clientCode, resourceType, subject, variant,
     *   sourceKeyHash, targetLanguageCodeGoogle)
     */
    public function enqueue(
        string $targetLangGoogle,
        string $sourceText,
        ?int $sourceStringId = null,
        string $clientCode = '',
        string $resourceType = '',
        string $subject = '',
        string $variant = '',
        string $stringKey = '',
        int $priority = 0,
        ?\DateTimeInterface $runAfter = null,
        string $sourceLangGoogle = 'en'
    ): bool {

        if ($targetLanguageGoogle == 'en'){
              LoggerService:logError('TQR.eng', [
                'method'        => __METHOD__ ,
                'function'      => __FUNCTION__ ,
                'line'          => __LINE__ ,
                'clientCode'    => $clientCode,
                'resourceType'  => $resourceType,
                'subject'       => $subject,
                'variant'       => $variant,
                'message'       => 'targeLanguageGoogle is en',
            ]);
            return false;
        }
        // Same hashing scheme as i18n_strings.keyHash (SHA-1 hex)
        $sourceKeyHash = sha1($stringKey !== '' ? $stringKey : $sourceText);

        $sql =
            "INSERT IGNORE INTO i18n_translation_queue
             (targetLanguageCodeGoogle, sourceText, status, attempts,
              runAfter, priority, queuedAt,
              sourceStringId, sourceLanguageCodeGoogle,
              clientCode, resourceType, subject, variant, stringKey,
              sourceKeyHash)
             VALUES
             (:lang, :text, 'queued', 0,
              :runAfter, :priority, UTC_TIMESTAMP(),
              :sid, :srcLang,
              :client, :rtype, :subject, :variant, :skey,
              :hash)";

        $params = [
            ':lang'    => $targetLangGoogle,
            ':text'    => $sourceText,
            ':priority'=> $priority,
            ':runAfter'=> $runAfter
                ? $runAfter->format('Y-m-d H:i:s')
                : date('Y-m-d H:i:s'),
            ':sid'     => $sourceStringId,
            ':srcLang' => $sourceLangGoogle,
            ':client'  => $clientCode,
            ':rtype'   => $resourceType,
            ':subject' => $subject,
            ':variant' => $variant,
            ':skey'    => $stringKey,
            ':hash'    => $sourceKeyHash,
        ];

        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt ? ($stmt->rowCount() > 0) : false;
    }

    /**
     * Atomically claim one runnable job for this worker (MariaDB 10.4 safe).
     */
    public function claimNextJob(string $worker): ?array
    {
        $this->db->beginTransaction();
        try {
            $pickSql =
                "SELECT id
                   FROM i18n_translation_queue
                  WHERE status = 'queued'
                    AND runAfter <= UTC_TIMESTAMP()
                    AND (lockedAt IS NULL
                         OR lockedAt < UTC_TIMESTAMP() - INTERVAL 5 MINUTE)
               ORDER BY priority DESC, runAfter ASC, id ASC
                  LIMIT 1";

            $updateSql =
                "UPDATE i18n_translation_queue q
                  JOIN ( $pickSql ) p ON p.id = q.id
                   SET q.status   = 'processing',
                       q.lockedBy = :w,
                       q.lockedAt = UTC_TIMESTAMP()";

            $updated = $this->db->executeQuery($updateSql, [':w' => $worker]);
            if (!$updated || $updated->rowCount() === 0) {
                $this->db->commit();
                return null;
            }

            $row = $this->db->fetchRow(
                "SELECT id,
                        targetLanguageCodeGoogle,
                        sourceLanguageCodeGoogle,
                        sourceText,
                        attempts,
                        priority,
                        runAfter,
                        queuedAt,
                        lockedBy,
                        lockedAt,
                        status,
                        sourceStringId,
                        clientCode,
                        resourceType,
                        subject,
                        variant,
                        stringKey,
                        sourceKeyHash
                   FROM i18n_translation_queue
                  WHERE lockedBy = :w
                    AND status   = 'processing'
               ORDER BY lockedAt DESC
                  LIMIT 1",
                [':w' => $worker]
            );

            $this->db->commit();
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            LoggerService::logError('claimNextJob', $e->getMessage());
            throw $e;
        }
    }

    /** Delete a completed job. */
    public function markDone(int $id): void
    {
        $this->db->executeQuery(
            "DELETE FROM i18n_translation_queue WHERE id = :id",
            [':id' => $id]
        );
    }

    /** Requeue a failed job with backoff. */
    public function markFailed(
        int $id,
        string $error = '',
        int $delaySeconds = 300
    ): void {
        $sql =
            "UPDATE i18n_translation_queue
                SET status   = 'queued',
                    attempts = attempts + 1,
                    runAfter = UTC_TIMESTAMP() + INTERVAL :s SECOND,
                    lockedBy = NULL,
                    lockedAt = NULL
              WHERE id = :id";

        $this->db->executeQuery($sql, [':s' => $delaySeconds, ':id' => $id]);
    }

    /** Release stale 'processing' jobs (crashed workers). */
    public function releaseStaleProcessing(int $minutes = 10): int
    {
        $stmt = $this->db->executeQuery(
            "UPDATE i18n_translation_queue
                SET status   = 'queued',
                    lockedBy = NULL,
                    lockedAt = NULL,
                    runAfter = UTC_TIMESTAMP()
              WHERE status   = 'processing'
                AND lockedAt < UTC_TIMESTAMP() - INTERVAL :m MINUTE",
            [':m' => $minutes]
        );
        return $stmt ? $stmt->rowCount() : 0;
    }
}
