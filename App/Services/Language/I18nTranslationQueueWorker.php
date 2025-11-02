<?php
declare(strict_types=1);

namespace App\Services\Language;

use App\Services\Database\DatabaseService;
use App\Services\LoggerService;

/**
 * I18nTranslationQueueWorker
 *
 * Reuse existing translations across resources (by keyHash).
 * If reuse fails and auto-MT is enabled for the target ISO (and a Google code
 * exists), call MT and upsert into i18n_translations as status='machine'.
 */
final class I18nTranslationQueueWorker
{
    private const MAX_ATTEMPTS    = 10;
    private const BACKOFF_MINUTES = [5, 15, 30, 60, 120, 240, 480, 1440, 2880, 4320];

    public function __construct(
        private DatabaseService $db,
        private TranslationBatchService $batch,
        private bool $autoMtEnabled = false,
        /** @var string[] ISO allow-list; if empty we just require googleCode to exist */
        private array $autoMtAllowGoogle = []
    ) {}

    /**
     * Process a single queue item if available.
     * @return bool true if a job was handled (success/pushback/failed) or grabbed by someone else;
     *              false if queue was empty.
     */
    public function processOne(string $workerName = 'cli-worker'): bool
    {
        $row = $this->db->fetchRow("
            SELECT *
              FROM i18n_translation_queue
             WHERE status = 'queued'
               AND runAfter <= UTC_TIMESTAMP()
             ORDER BY priority DESC, id ASC
             LIMIT 1
        ");
        if (!$row) return false;

        $id = (int)$row['id'];

        // Lock
        $locked = $this->db->executeQuery("
            UPDATE i18n_translation_queue
               SET status   = 'processing',
                   lockedBy = :who,
                   lockedAt = UTC_TIMESTAMP(),
                   attempts = attempts + 1
             WHERE id = :id
               AND status = 'queued'
        ", [':who' => $workerName, ':id' => $id]);
        LoggerService::logDebugI18n('ITQW.translate', [
            'method'   => __METHOD__ ,
            'function' => __FUNCTION__ ,
            'line'     => __LINE__ ,
            'texts' => $texts,
            'targetLanguage' => $targetLanguage,
            'sourceLanguage' => $sourceLanguage,
            'format'=> $format
        ]);
        if ($locked === 0) return true;

        $attempts = ((int)($row['attempts'] ?? 0)) + 1;

        try {
            // Ensure stringId
            $stringId = !empty($row['sourceStringId']) ? (int)$row['sourceStringId'] : null;
            if ($stringId === null) {
                $resourceId = $this->resolveResourceId(
                    (string)$row['resourceType'],
                    (string)$row['subject'],
                    $row['variant'] === '' ? null : (string)$row['variant']
                );
                if ($resourceId > 0) {
                    $sid = $this->db->fetchValue("
                        SELECT stringId
                          FROM i18n_strings
                         WHERE resourceId = :rid
                           AND keyHash    = :hash
                         LIMIT 1
                    ", [':rid' => $resourceId, ':hash' => (string)$row['sourceKeyHash']]);
                    $stringId = $sid ? (int)$sid : null;
                }
            }
            if ($stringId === null) {
                $this->pushBack($id, $this->backoffMinutes($attempts), 'missing stringId');
                return true;
            }

            // Try to reuse by keyHash (best quality first)
            $reused = $this->reuseExistingIfPossible(
                stringId:  $stringId,
                keyHash:   (string)$row['sourceKeyHash'],
                targetGoogle: (string)$row['targetLanguageCodeGoogle'],
                targetHl:  null // add if you queue it
            );
            if ($reused) {
                $this->deleteJob($id);
                return true;
            }

            // Auto-MT gate
            $targetGoogle = (string)$row['targetLanguageCodeGoogle'];
            [$targetHl, $googleCode] = $this->getCodesForGoogle($targetGoogle); // HL may be null; googleCode required for MT

            $googleAllowed = empty($this->autoMtAllowGoogle) || in_array($targetGoogle, $this->autoMtAllowGoogle, true);
            $canMt = $this->autoMtEnabled && $googleAllowed && ($googleCode !== null && $googleCode !== '');

            if ($canMt) {
                $ok = $this->mtTranslateAndUpsert(
                    stringId:      $stringId,
                    targetGoogle:  $targetGoogle,
                    targetHl:      $targetHl, // can be null
                    googleCode:    $googleCode,
                    sourceEnglish: (string)$row['sourceText']
                );

                if ($ok) {
                    $this->deleteJob($id);
                    return true;
                }
            }

            // Backoff or fail
            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->markFailed($id, $canMt ? 'mt-failed' : 'no-reuse-no-mt');
                return true;
            }
            $this->pushBack($id, $this->backoffMinutes($attempts), $canMt ? 'mt-retry' : 'no-reuse');
            return true;

        } catch (\Throwable $e) {
            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->markFailed($id, 'exception: '.$e->getMessage());
            } else {
                $this->pushBack($id, $this->backoffMinutes($attempts), 'exception');
            }
            return true;
        }
    }

    // ---------------- internals ----------------

    /** Attempt to copy an existing translation for identical English (by keyHash). */
    private function reuseExistingIfPossible(
        int $stringId,
        string $keyHash,
        string $targetGoogle,
        ?string $targetHl
    ): bool {
        $sql = "
        INSERT INTO i18n_translations
          (stringId, languageCodeHL, languageCodeGoogle, translatedText, status, source, posted)
        SELECT
          :sid,
          t.languageCodeHL,
          t.languageCodeGoogle,
          t.translatedText,
          t.status,
          'import' AS source,
          CURDATE() AS posted
        FROM i18n_strings s
        JOIN i18n_translations t ON t.stringId = s.stringId
        WHERE s.keyHash = :hash
          AND (t.languageCodeGoogle = :google " . ($targetHl ? " OR t.languageCodeHL = :hl " : "") . ")
          AND t.translatedText <> ''
        ORDER BY FIELD(t.status,'approved','review','machine','draft') ASC
        LIMIT 1
        ON DUPLICATE KEY UPDATE
          translatedText = VALUES(translatedText),
          status         = IF(i18n_translations.status='approved', i18n_translations.status, VALUES(status)),
          source         = 'import',
          updatedAt      = CURRENT_TIMESTAMP
        ";
        $params = [':sid' => $stringId, ':hash' => $keyHash, ':google' => $targetGoogle];
        if ($targetHl) $params[':hl'] = $targetHl;

        $rows = $this->db->executeQuery($sql, $params);
        return $rows > 0;
    }

    /** Resolve resourceId for (type, subject, variant). */
    private function resolveResourceId(string $type, string $subject, string $variant='default'): int
    {
        $rid = $this->db->fetchValue("
            SELECT resourceId
              FROM i18n_resources
             WHERE type = :type
               AND subject = :subject
               AND (variant <=> :variant)
             LIMIT 1
        ", [
            ':type'    => $type,
            ':subject' => $subject,
            ':variant' => ($variant === null || $variant === '') ? null : $variant,
        ]);
        return $rid ? (int)$rid : 0;
    }

    /** Look up HL + Google code by ISO; returns [hl|null, google|null]. */
    private function getCodesForGoogle(string $google): array
    {
        // camelCase columns; adjust if your hl_languages differs
        $row = $this->db->fetchRow("
            SELECT languageCodeHL, languageCodeGoogle
              FROM hl_languages
             WHERE languageCodeGoogle = :google
             LIMIT 1
        ", [':google' => $google]);

        $hl  = is_array($row) ? (string)($row['languageCodeHL'] ?? '') : '';
        $ggl = is_array($row) ? (string)($row['languageCodeGoogle'] ?? '') : '';

        return [
            $hl !== '' ? $hl : null,
            $ggl !== '' ? $ggl : null,
        ];
    }

    /** Call MT and upsert into i18n_translations as status='machine'. */
    private function mtTranslateAndUpsert(
        int $stringId,
        string $targetGoogle,
        ?string $targetHl,
        string $googleCode,
        string $sourceEnglish
    ): bool {
        $arr = $this->batch->translateBatch([$sourceEnglish], $googleCode, 'en', 'text');
        $mt = $arr[0] ?? '';
        if ($mt === '') return false;

        $sql = "
            INSERT INTO i18n_translations
                (stringId, languageCodeHL, languageCodeGoogle, translatedText, status, source, posted)
            VALUES
                (:sid, :hl, :google, :txt, 'machine', 'mt', CURDATE())
            ON DUPLICATE KEY UPDATE
                -- never downgrade approved
                translatedText = IF(i18n_translations.status='approved',
                                    i18n_translations.translatedText,
                                    VALUES(translatedText)),
                status         = IF(i18n_translations.status='approved',
                                    i18n_translations.status,
                                    'machine'),
                source         = 'mt',
                updatedAt      = CURRENT_TIMESTAMP
        ";
        $this->db->executeQuery($sql, [
            ':sid' => $stringId,
            ':hl'  => $targetHl ?? '',
            ':google' => $targetGoogle,
            ':txt' => $mt,
        ]);
        return true;
    }

    private function deleteJob(int $id): void
    {
        $this->db->executeQuery("DELETE FROM i18n_translation_queue WHERE id = :id", [':id' => $id]);
    }

    private function pushBack(int $id, int $minutes, string $note = ''): void
    {
        $this->db->executeQuery("
            UPDATE i18n_translation_queue
               SET status   = 'queued',
                   lockedBy = NULL,
                   lockedAt = NULL,
                   runAfter = DATE_ADD(UTC_TIMESTAMP(), INTERVAL :m MINUTE)
             WHERE id = :id
        ", [':m' => $minutes, ':id' => $id]);
    }

    private function markFailed(int $id, string $reason = ''): void
    {
        $this->db->executeQuery("
            UPDATE i18n_translation_queue
               SET status   = 'failed',
                   lockedBy = NULL,
                   lockedAt = NULL
             WHERE id = :id
        ", [':id' => $id]);
    }

    private function backoffMinutes(int $attempts): int
    {
        $idx = max(1, min($attempts, count(self::BACKOFF_MINUTES)));
        return self::BACKOFF_MINUTES[$idx - 1];
    }
}
