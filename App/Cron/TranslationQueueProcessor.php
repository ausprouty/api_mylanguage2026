<?php
declare(strict_types=1);

namespace App\Cron;
use DateInterval;
use DateTimeImmutable;
use Exception;
use PDO;
use PDOException;
use App\Configuration\Config;
use App\Contracts\Templates\TemplateAssemblyService;
use App\Contracts\Translation\TranslationProvider as ProviderContract;
use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use App\Support\i18n\ExcludeKeyMatcher;


/**
 * TranslationQueueProcessor
 *
 * Cron-safe worker for i18n_translation_queue:
 * - Picks eligible rows by priority and runAfter
 * - Locks atomically (statuslockedBylockedAt)
 * - Calls a TranslationProvider to obtain MT text
 * - Upserts into i18n_translations (UNIQUE by (stringId, language))
 * - Deletes the queue row on success
 * - On failure, increments attempts and sets exponential backoff
 *
 * Tables:
 *  - i18n_translation_queue
 *    (id, sourceStringId, sourceLanguageCodeGoogle, clientCode,
 *     resourceType, subject, variant, stringKey, sourceKeyHash,
 *     targetLanguageCodeGoogle, sourceText, status, lockedBy, lockedAt,
 *     attempts, runAfter, priority, queuedAt)
 *
 *  - i18n_translations
 *    (translationId, stringId, languageCodeGoogle, translatedText,
 *     status, source, translator, reviewedBy, posted, createdAt, updatedAt)
 */



final class TranslationQueueProcessor
{

    private bool $debugProcessor = true;

    /** @var array<string,string> normalized DB filters 
     * Runner may pass normalized DB filters to prioritize work, e.g.:
     *  - targetLanguageCodeGoogle, clientCode, resourceType, subject, variant
     * These are NOT hard filters; they bias the ORDER so matches are first.
     */
    private array $scopeFilters = [];
   
    /** @var int */
    private $batchSize = 25;

    /** @var int max attempts before marking failed permanently */
    private $maxAttempts = 6;

    /** @var string ISO 8601 backoff base (2^n minutes) */
    private $backoffUnit = 'PT1M';

    /** @var string */
    private $workerId;

      /** @var array<string,string> */
      
    private bool $dryRun = false;

    // counters
    private int $attempted = 0;
    private int $succeeded = 0;
    private int $retryable = 0;
    private int $permanent = 0;

    public function __construct(
        private DatabaseService $db,
        private LoggerService $logger,
        private ProviderContract $translator, 
        private TemplateAssemblyService $templateAssembly,

         /** Prefer DI via setPdo(); otherwise we lazy-resolve. */
        private ?PDO $pdo = null
       
    ) {
        // Keep worker id short for UNIQUE index lengths.
        $host = php_uname('n');
        $pid  = (string) getmypid();
        $this->workerId = substr("cron:$host:$pid", 0, 64);

        // Log which class file is actually in use (path + short hash)
        $rc = new \ReflectionClass($this);
        $file = $rc->getFileName();
        $hash = is_file($file) ? substr(sha1_file($file), 0, 12) : 'missing';
        LoggerService::logDebugI18n('TQP.version', ['file' => $file, 'hash' => $hash]);
        
    }
        /** Allow the runner to inject a PDO handle. */
    public function setPdo(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    /**
     * Run one cron tick: pick and process up to $batchSize items.
     */
    public function runOnce(): void
    {
        // Do not run if in maintenance
        if (Config::getBool('i18n.maintenance', false)) {
            LoggerService::logDebugI18n('TQP.maintenance_skip');
        return;

        // Log which class file is actually running (helps detect stale copies)
        $rc   = new \ReflectionClass($this);
        $file = $rc->getFileName();
        $hash = is_file($file) ? substr(sha1_file($file), 0, 12) : 'missing';
        LoggerService::logDebugI18n('TQP.version', ['file' => $file, 'hash' => $hash]);
  
}
        if ($this->pdo === null) {
            $this->pdo = $this->resolvePdo();
            if ($this->pdo === null) {
                LoggerService::logDebugI18n(
                    'TQP.runOnce.noPdo',
                    ['err' => 'resolvePdo() failed']
                );
                return;
            }
        }


        // Log the effective scope and mode at the start of each cycle.
        LoggerService::logDebugI18n(
            'TQP.runOnce',
            [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'scope'    => $this->scopeFilters,
                'dryRun'   => $this->dryRun,
                'batchSize'    => $this->batchSize ?? null,
            ]
        );

         // ... existing logic that selects a batch respecting:
         // status='queued', locks, runAfter<=NOW(), attempts policy, etc.
         // Make sure your WHERE adds the filters in $this->scopeFilters.
 
         // Example (where you build your SELECT), add a quick count log:
         // LoggerService::logDebugI18n('TQP.select', [
         //     'where' => $whereSql, 'params' => $params
         // ]);
 
         // If $this->dryRun is true, skip writes (upsert/delete),
         // but still log what *would* have happened:
         // if ($this->dryRun) { LoggerService::logDebugI18n('TQP.dry.ids', $ids);

       // Build WHERE for "eligible" rows (existing project rules).
        $where = [];
        $params = [];
        $where[] = "(COALESCE(status,'queued') = 'queued')";
        $where[] = "((lockedBy IS NULL) OR (lockedAt < NOW() - INTERVAL 30 MINUTE))";
        $where[] = "((runAfter IS NULL) OR (runAfter <= NOW()))";

        // Optional: your policy may also exclude too-many attempts, etc.
        // $where[] = "(attempts < :maxAttempts)"; $params[':maxAttempts'] = 8;

        $whereSql = implode(' AND ', $where);
        // Priority: rows matching the given scope first, then others.
        // We compute a CASE expression that yields 0 when all provided
        // scope keys match, 1 otherwise. Missing keys are ignored.
        $priorityParts = [];
        foreach ([
            'targetLanguageCodeGoogle',
            'clientCode',
            'resourceType',
            'subject',
            'variant',
        ] as $k) {
            // inside the loop that processes each $job:

            if (isset($this->scopeFilters[$k])) {
                $priorityParts[] = sprintf(
                    "(%s = :scope_%s)",
                    $k,
                    $k
                );
                $params[":scope_{$k}"] = $this->scopeFilters[$k];
            }
        }

        // If no scope parts, priority is always 1 (no bias).
        // If there are parts, require ALL to match to get priority 0.
        $priorityExpr = '1';
        if ($priorityParts) {
            $priorityExpr = 'CASE WHEN ' . implode(' AND ', $priorityParts) . ' THEN 0 ELSE 1 END';
        }

        
       
        // -- ORDER BY safety: normalize & whitelist ---------------------------------
        // Accepts string keys like: id | queuedAt | priority | runAfter (extendable)
        // Guard against values coming in as arrays/bools/ints from getopt/config.
        $rawPriorityExpr = $priorityExpr;
        if (is_array($priorityExpr)) {
            // e.g., Array([0] => 1) from getopt short flags
            $priorityExpr = reset($priorityExpr);
        }
        // Cast to string and trim
        $priorityExpr = trim((string)$priorityExpr);
        // Map friendly keys to safe SQL fragments (no user-supplied SQL allowed)
        $allowedOrderMap = [
            '1'        => 'id',
            '2'        => 'queuedAt',
            '3'        => 'priority',
            '4'        => 'runAfter',
            'id'       => 'id',
            'queuedAt' => 'queuedAt',
            'priority' => 'priority',
            'runAfter' => 'runAfter',   // add if you have this column and want it
        ];
        // Provide a sane default if value is empty or not recognized
        if ($priorityExpr === '' || !isset($allowedOrderMap[$priorityExpr])) {
            LoggerService::logError('TQP.Bad_priorityExpr', [
                'received' => $rawPriorityExpr,
                'normalized' => $priorityExpr,
                'allowed' => array_keys($allowedOrderMap),
                'note' => 'Falling back to default "queuedAt"',
            ]);
            $priorityExpr = 'queuedAt';
        }
        $priorityExprSql = $allowedOrderMap[$priorityExpr];

        // Typical tie-breakers after priority:
        //   - higher "priority" (your column) first (DESC)
        //   - older queuedAt first (ASC)
        // NOTE: MySQL native prepares do not allow binding LIMIT/OFFSET.
        // Inline a safe int to avoid returning zero rows.
        $limit = max(1, (int)($this->batchSize ?? 100));
        $sql = "
            SELECT id, resourceType, subject, variant, stringKey
            FROM i18n_translation_queue
            WHERE {$whereSql}
            ORDER BY
                {$priorityExprSql} ASC,
                priority DESC,
                queuedAt ASC
            LIMIT {$limit}
        ";

        /** @var PDO $this->pdo */
        $stmt = $this->pdo->prepare($sql);
        if (!empty($params)) {
            LoggerService::logError('TQP.params_ignored_no_placeholders', ['params' => array_keys($params)]);
        }
        if (!$stmt->execute()) {
            $err = $stmt->errorInfo();
            LoggerService::logError('TQP.select_execute_failed', ['errorInfo' => $err, 'sql' => $sql]);
            usleep(200 * 1000); // back off on persistent error
            return;
        }
        
        $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        LoggerService::logDebugI18n('TQP.selected', [
            'method'   => __METHOD__ ,
            'function' => __FUNCTION__ ,
            'line'     => __LINE__ ,
            'count'    => count($jobs), 
            'limit'    => $limit, 
            'sql'     => $sql,
            
        ]);
        
        if (!$jobs) {
                LoggerService::logDebugI18n('TQP.noJobs', [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'sql'     =>  $sql]
            );
            usleep((200 + random_int(0, 150)) * 1000); // 200–350ms // avoid tight-loop log spam when queue is empty
            return;
        }

        // Get the DB-side total with the SAME WHERE (no LIMIT)
        $countSql  = "SELECT COUNT(*) FROM i18n_translation_queue WHERE {$whereSql}";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

       // Debug log (lazy context so it’s free when disabled)
        LoggerService::logDebugI18n('TQP.selected', function () use ($jobs, $limit, $total, $params) {
            // keep params serializable
            $safeParams = [];
            foreach ($params as $k => $v) {
                $safeParams[$k] = is_scalar($v) || $v === null ? $v : gettype($v);
            }
            return [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'count'    => count($jobs),  // what we actually fetched
                'total'    => $total,        // how many matched before LIMIT
                'limit'    => $limit,
                'params'   => $safeParams,
            ];
        });
        // Optional: quick peek at the first few IDs to prove selection
            LoggerService::logDebugI18n('TQP.selected.id.first.10', function () use ($jobs) {
                return [
                    'method'   => __METHOD__ ,
                    'function' => __FUNCTION__ ,
                    'line'     => __LINE__ ,
                    'ids' => array_slice(array_column($jobs, 'id'), 0, 10)
                ];
            });

        if (!$jobs) { 
            LoggerService::logDebugI18n('TQP.jobs.none', [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'message' => 'no jobs']);
            return;
         }

        // --- Exclude guard: drop video.*, meta.*, etc. per bundle rules -----
        $exCache = [];   // key: "type|subject|variant" => ExcludeKeyMatcher
        $okJobs  = [];
        foreach ($jobs as $job) {
            $type    = (string)$job['resourceType'];
            $subject = (string)$job['subject'];
            $variant = ($job['variant'] === '' ? null : $job['variant']);
            $cacheK  = $type . '|' . $subject . '|' . ($variant ?? '');

            if (!isset($exCache[$cacheK])) {
                $base = $this->templateAssembly->assemble($type, $subject, $variant);
                // Merge env defaults with bundle list (in case loader didn’t):
                $defaults = (array)\App\Configuration\Config::get('i18n.exclude_keys_default', []);
                $bundleEx = (array)($base['meta']['i18n']['excludeKeys'] ?? []);
                $exCache[$cacheK] = new ExcludeKeyMatcher(array_merge($defaults, $bundleEx));
            }
            $mx = $exCache[$cacheK];
            $dotKey = (string)$job['stringKey'];
            if ($mx->isExcluded($dotKey)) {
                // mark ignored (or DELETE if you prefer)
                $upd = $this->pdo->prepare(
                    "UPDATE i18n_translation_queue SET status='ignored' WHERE id = :id AND status='queued'"
                );
                $upd->execute([':id' => (int)$job['id']]);
                continue;
            }
            $okJobs[] = $job;
        }

        if (!$okJobs) { return; }

        // Continue with your existing lock/translate/upsert/delete flow,
        // but only for $okJobs:
        $ids = array_map(static fn($j) => (int)$j['id'], $okJobs);
        // ... existing locking and processing using $ids ...

        // ... proceed with your existing lock/translate/upsert/delete flow
        //     using the $ids selected above.
 

        LoggerService::logDebugI18n('TQP.resetCounters',
            ['method'   => __METHOD__ ,
            'function' => __FUNCTION__ ,
            'line'     => __LINE__ , 
            'message'  => 'started process'
        ]);
                // reset per-run stats
        $this->attempted = 0;
        $this->succeeded = 0;
        $this->retryable = 0;
        $this->permanent = 0;
        $started = microtime(true);
        try {
            $jobs = $this->lockBatch();
            LoggerService::logDebugI18n('TQP.lockbatch.returned', 
                ['method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ , 
                'message'  => 'started process',
                'jobs' => $jobs 
            ]);
        } catch (Throwable $e) {
            LoggerService::logError('TQP lockBatch failed', [
                'err' => $e->getMessage(),
            ]);
            return;
        }

        if (!$jobs) {
            LoggerService::logDebugI18n('TQP:jobs.none',  [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ , 
                'message'  =>'TQP: no eligible jobs.'
            ]);
            return;
        }

        foreach ($jobs as $job) {
            LoggerService::logDebugI18n('TQP.job', [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ , 
                'message'  => 'calling processOne', 
                'job'=>$job
            ]);
            $this->processOne($job);
        }
             // Emit per-run stats before final timing line
        LoggerService::logDebugI18n('TQP: stats', [
            'method'   => __METHOD__ ,
            'function' => __FUNCTION__ ,
            'line'     => __LINE__ , 
            'attempts'  => $this->attempted,
            'success'   => $this->succeeded,
            'retry'     => $this->retryable,
            'permanent' => $this->permanent,
        ]);
 

        $elapsedMs = (int) ((microtime(true) - $started) * 1000);
        LoggerService::logDebugI18n('TQP: batch complete', [
            'picked'  => count($jobs),
            'elapsed' => $elapsedMs . 'ms',
        ]);
    }

    /**
     * Atomically claim a batch:
     *  - status = queued
     *  - runAfter <= now
     *  - oldest first by (priority desc, id asc)
     * Converts to status=processing and sets lockedBy/lockedAt.
     *
     * Uses a two-step approach for broad MariaDB compatibility.
     *
     * @return array<int, array<string,mixed>>
     */
    private function lockBatch(): array
    {
        $limit = (int) ($this->batchSize ?? 100);
        if ($limit < 1) {
            $limit = 1;
        }
        // Optional upper cap to avoid absurd limits
        if ($limit > 120) {
            $limit = 120;
        }
        $this->pdo->beginTransaction();
        try {
            // Step 1: read candidate ids
            $sql = '
                SELECT id
                FROM i18n_translation_queue
                WHERE status = "queued"
                AND runAfter <= UTC_TIMESTAMP()
                AND (lockedAt IS NULL
                        OR lockedAt < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE))
            ORDER BY priority DESC, id ASC
                LIMIT ' . $limit;

            $sel = $this->pdo->prepare($sql);
            $sel->execute();
            $ids = $sel->fetchAll(PDO::FETCH_COLUMN, 0);

            if (!$ids) {
                $this->pdo->commit();
                return [];
            }

            // Step 2: attempt to lock those ids atomically
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $upd = $this->pdo->prepare(
                "UPDATE i18n_translation_queue
                    SET status = 'processing',
                        lockedBy = ?,
                        lockedAt = UTC_TIMESTAMP()
                  WHERE id IN ($in)
                    AND status = 'queued'
                    AND runAfter <= UTC_TIMESTAMP()
                    AND (lockedAt IS NULL
                         OR lockedAt < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE))"
            );
            
            $bind = [$this->workerId];
            foreach ($ids as $id) {
                $bind[] = (int) $id;
            }
            LoggerService::logDebugI18n('TQP.LockBatch.processing.set',
                ['method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ , 
                'in'  => $in,
                'bind' => $bind
            ]); 
            $upd->execute($bind);

            // Step 3: fetch what we actually locked
            $sel2 = $this->pdo->prepare(
                "SELECT *
                   FROM i18n_translation_queue
                  WHERE id IN ($in)
                    AND status = 'processing'
                    AND lockedBy = ?"
            );
            $bind2 = [];
            foreach ($ids as $id) {
                $bind2[] = (int) $id;
            }
            $bind2[] = $this->workerId;
            LoggerService::logDebugI18n('TQP.LockBatch.processing.get',
                ['method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ , 
                'in'  => $in,
                'bind' => $bind2
            ]); 
            $sel2->execute($bind2);
            $jobs = $sel2->fetchAll(PDO::FETCH_ASSOC);
             LoggerService::logDebugI18n('TQP.LockBatch.jobs',
                ['method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ , 
                'jobs'  => $jobs,
            ]); 

            $this->pdo->commit();
            return $jobs ?: [];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    

    public function setTranslator(ProviderContract $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * Process a single locked  row.
     * /**
    * @param array{
    *   id:int,
    *   sourceStringId:int|null,
    *   sourceLanguageCodeGoogle:string,
    *   targetLanguageCodeGoogle:string,
    *   clientCode:string,
    *   resourceType:string,
    *   subject:string,
    *   variant:string,
    *   stringKey:string,
    *   sourceKeyHash:string,
    *   sourceText:string,
    *   status:string,
    *   attempts:int,
    *   runAfter:string,
    *   priority:int,
    *   lockedBy?:string|null,
    *   lockedAt?:string|null
    * } $row
    *
     *
     * @param array<string,mixed> $row
     */
    private function processOne(array $row): void
    {
        ++$this->attempted;
        $id     = (int) $row['id'];
        LoggerService::logDebugI18n('TQP.LockBatch.jobs',
                ['method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ , 
                'row'  => $row,
                'id'  => $id,
            ]); 

        $sourceLang = $row['sourceLanguageCodeGoogle'] ?: 'en';
        $targetLang = $row['targetLanguageCodeGoogle'];
        $sourceText = (string)$row['sourceText'];

        $stringId = (int)($row['sourceStringId'] ?? 0);
        if ($stringId === 0) {
            $stringId = $this->ensureStringId($row);
        }

        // Guard: need a target and text; sourceStringId is required for the
        // translations table schema.
        if ($stringId === null || $targetLang === '' ||  $sourceText === '') {
            LoggerService::logDebugI18n('TQP.parameters.null', [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'stringId'     => $id,
                'targetLang' => $targetLang,
                'sourceText' => $sourceText,
            ]);
            $this->failPermanently($id, 'invalid-queue-row');
            return;
        }
         LoggerService::logDebugI18n('TQP:translating', [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'targetLang' => $targetLang,
                'sourceText' => [$sourceText],
            ]);

        [$ok, $out, $httpCode, $errMsg, $respLen] = 
           $this->translator->translate(
                [$sourceText],                              // inputs
                $targetLang,                                // target language
                $sourceLang,                                // source language
                 \App\Contracts\Translation\TranslationProvider::FORMAT_TEXT 
            );
            // --- provider call (replace this with however you already call it) ---


        // Normalize result
        $translatedText = is_string($out) ? trim($out) : '';

        // Decide outcome
        $success = ($ok === true)
            && $httpCode >= 200 && $httpCode < 300
            && $translatedText !== '';

        if ($success) {
            LoggerService::logDebugI18n('TQ-success', [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'stringId'  => $stringId,
                'lang'      => $targetLang,
                'len'       => mb_strlen($translatedText),
                'http'      => $httpCode,
                'jobId'     => $id,
            ]);
        

            $this->upsertTranslation(
                $stringId,
                $targetLang,
                $translatedText,   // guaranteed string
                'mt',
                'cron:' . $this->workerId
            );

            $this->deleteQueueRow($id);
            ++$this->succeeded;
            LoggerService::logDebugI18n('TQ-acked', ['id' => $id]);
            return;
        }

        // Not a success → classify and handle
        $transient = $this->isTransientFailure($httpCode, $errMsg);

        $diag = [
            'jobId'     => $id,
            'stringId'  => $stringId,
            'lang'      => $targetLang,
            'http'      => $httpCode,
            'ok'        => $ok,
            'err'       => $errMsg,
            'respLen'   => $respLen,
            'outType'   => gettype($out),
            'outSample' => is_string($out) ? mb_substr($out, 0, 80) : null,
        ];

        if ($transient) {
            LoggerService::logDebugI18n('TQ-retry', [$diag]);
            ++$this->retryable;
            $this->requeueWithBackoff($job);
            return;
        }

        // Permanent failure → dead-letter (or mark failed without retry)
        LoggerService::logError('TQ-dead', $diag);
        ++$this->permanent;
        $this->deadLetter($id, $diag);
        return;

    }

        /**
     * Dead-letter handler: mark as failed permanently (no retry).
     * Extend here later if you want a dedicated DLQ table.
     *
     * @param array<string,mixed> $job
     * @param array<string,mixed> $diag
     */
    private function deadLetter(int $id, array $diag): void
    {
        if ($id <= 0) { return; }
        LoggerService::logDebugI18n(
            'TQ.dead.permanent',
            $diag + ['reason' => 'provider-hard-fail', 'id' => $id]
        );
        $this->failPermanently($id, 'provider-hard-fail');
    }


       /**
     * Decide if a failure should be retried (transient).
     *
     * Inputs:
     *   $http — HTTP status code; null when no HTTP layer is present.
     *   $err  — Optional error text; scanned for transient hints.
     *
     * Rules (first match wins):
     *   0          → transport/network (retry)
     *   408, 429   → timeout / rate limit (retry)
     *   5xx        → upstream/server error (retry)
     *   /timeout|temporar|reset|quota|rate/i → retry
     * Otherwise: permanent (no retry).
     */
    private function isTransientFailure(?int $http, ?string $err): bool
    {
        if ($http === 0) return true;                  // network/transport
        if ($http === 408) return true;                // request timeout
        if ($http === 429) return true;                // rate limited
        if ($http !== null && $http >= 500) return true; // server errors

        if ($err && preg_match('/timeout|temporar|reset|quota|rate/i', $err)) {
            return true;
        }
        return false; // everything else treat as permanent
    }


    private function upsertTranslation(
        int $stringId,
        string $languageCodeGoogle,
        string $translatedText,
        string $source,
        string $translator
    ): void {

        $sql = <<<SQL
            INSERT INTO i18n_translations
            (stringId, languageCodeGoogle, translatedText, status, source, translator, posted)
            VALUES
            (:sid,     :lang,              :txt,            :status, :src,   :who,       UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
            translatedText = VALUES(translatedText),
            status         = VALUES(status),
            source         = VALUES(source),
            translator     = VALUES(translator),
            updatedAt      = UTC_TIMESTAMP()
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sid'    => $stringId,
            ':lang'   => $languageCodeGoogle,
            ':txt'    => $translatedText,
            ':status' => 'machine',
            ':src'    => $source,
            ':who'    => $translator,
        ]);
    }

    private function deleteQueueRow(int $id): void
    {
        $del = $this->pdo->prepare(
            'DELETE FROM i18n_translation_queue
              WHERE id = :id AND lockedBy = :who'
        );
        $del->execute([
            ':id'  => $id,
            ':who' => $this->workerId,
        ]);
    }

    /**
     * Increment attempts and set runAfter with exponential backoff.
     * Attempts >= maxAttempts => mark as failed permanently.
     *
     * @param array<string,mixed> $job
     */
    private function requeueWithBackoff(array $job): void
    {
        $id       = (int) $job['id'];
        $attempts = (int) $job['attempts'] + 1;

        if ($attempts >= $this->maxAttempts) {
             LoggerService::logDebugI18n('TQP.attempts.tooMany', [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'id'     => $id,
                'reason' => $attempts,
            ]);
            $this->failPermanently($id, 'max-attempts');
            return;
        }

        $now  = new DateTimeImmutable('now');
        $base = new DateInterval($this->backoffUnit); // 1 minute
        $mins = 1 << ($attempts - 1); // 1,2,4,8,16,32
        $runAfter = $now->add(
            new DateInterval('PT' . (string) $mins . 'M')
        );

        $upd = $this->pdo->prepare(
            'UPDATE i18n_translation_queue
                SET status   = "queued",
                    attempts = :a,
                    lockedBy = NULL,
                    lockedAt = NULL,
                    runAfter = :ra
              WHERE id = :id'
        );
        $upd->execute([
            ':a'  => $attempts,
            ':ra' => $runAfter->format('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    private function failPermanently(int $id, string $reason): void
    {
        $upd = $this->pdo->prepare(
            'UPDATE i18n_translation_queue
                SET status   = "failed",
                    lockedBy = NULL,
                    lockedAt = NULL
              WHERE id = :id'
        );
        $upd->execute([':id' => $id]);
        LoggerService::logDebugI18n('TQP: failed permanently', [
            'method'   => __METHOD__ ,
            'function' => __FUNCTION__ ,
            'line'     => __LINE__ ,
            'id'     => $id,
            'reason' => $reason,
        ]);
    }

    private function pdo(): PDO
    {
        // DatabaseService should expose a PDO with ERRMODE_EXCEPTION
        return $this->db->getPdo();
    }

    public function setBatchSize(int $n): void {
        $this->batchSize = max(1, $n);
    }

    private function ensureStringId(array $row): int
    {

        LoggerService::logDebugI18n('TQP.ensureStringId',[ 
            'method'   => __METHOD__ ,
            'function' => __FUNCTION__ ,
            'line'     => __LINE__ ,
            'row'     => $row,
            'reason' => $reason
        ]);
        $this->pdo->beginTransaction();

        try {
            // 0) Resolve FKs from human-friendly fields
            $clientId   = $this->resolveClientId((string)$row['clientCode']);
            LoggerService::logDebugI18n('TQP ensureStringId', $clientId);
            $resourceId = $this->resolveResourceId(
                (string)$row['resourceType'],
                (string)$row['subject'],
                (string)$row['variant']
            );
            LoggerService::logDebugI18n('TQP.ensureStringId',[
                'method'       => __METHOD__ ,
                'function'     => __FUNCTION__ ,
                'line'         => __LINE__ ,
                'resourceId'   => $resourceId
            ]);

            $keyHash     = (string)$row['sourceKeyHash'];  // 40-char sha1
            $englishText = (string)$row['sourceText'];     // queue has the source text

            // 1) Find existing string by (clientId, resourceId, keyHash)
            $sel = $this->pdo->prepare(
                "SELECT stringId
                FROM i18n_strings
                WHERE clientId = ? AND resourceId = ? AND keyHash = ?"
            );
            $sel->execute([$clientId, $resourceId, $keyHash]);
            $stringId = (int) ($sel->fetchColumn() ?: 0);
            LoggerService::logDebugI18n('TQP.ensureStringId',[ 
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'stringId' => $stringId 
            ]);

            // 2) If not found, insert it
            if ($stringId === 0) {
                $ins = $this->pdo->prepare(
                    "INSERT INTO i18n_strings
                    (clientId, resourceId, keyHash, englishText, developerNote, isActive, createdAt)
                    VALUES
                    (:cid, :rid, :kh, :en, NULL, 1, CURRENT_TIMESTAMP)"
                );
                $ins->execute([
                    ':cid' => $clientId,
                    ':rid' => $resourceId,
                    ':kh'  => $keyHash,
                    ':en'  => $englishText,
                ]);
                $stringId = (int)$this->pdo->lastInsertId();
            }

            // 3) Persist back to queue (note: queue table has no updatedAt column)
            $upd = $this->pdo->prepare(
                "UPDATE i18n_translation_queue
                    SET sourceStringId = :sid
                WHERE id = :qid"
            );
            $upd->execute([
                ':sid' => $stringId,
                ':qid' => (int)$row['id'],
            ]);

            $this->pdo->commit();
            LoggerService::logDebugI18n('TQP ensureStringId.out', [
                'queueId'  => (int)$row['id'],
                'stringId' => $stringId,
                'clientId' => $clientId,
                'resourceId' => $resourceId,
            ]);
    

            return $stringId;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
}

    /**
     * Resolve or create a clientId from clientCode.
     */
    private function resolveClientId(string $clientCode): int{
        // Try existing
        $sel = $this->pdo->prepare("SELECT clientId FROM i18n_clients WHERE clientCode = ?");
        $sel->execute([$clientCode]);
        $id = (int) ($sel->fetchColumn() ?: 0);
        if ($id > 0) return $id;

        // Insert-or-select for race-safety
        $ins = $this->pdo->prepare("INSERT IGNORE INTO i18n_clients (clientCode) VALUES (?)");
        $ins->execute([$clientCode]);

        if ($this->pdo->lastInsertId() !== '0') {
            return (int)$this->pdo->lastInsertId();
        }

        // Someone else inserted between our SELECT and INSERT
        $sel->execute([$clientCode]);
        $id = (int) ($sel->fetchColumn() ?: 0);
        if ($id > 0) return $id;

        throw new \RuntimeException("Failed to resolve clientId for clientCode={$clientCode}");
    }

    /*
    * Resolve or create a resourceId from (type, subject, variant).
    * `variant` may be NULL (unique key is on (type, subject, variant)).
    */
    private function resolveResourceId(
        string $type,
        string $subject,
        string $variant = 'default'
    ): int {
        if (!$this->pdo instanceof \PDO) {
            throw new \RuntimeException('PDO not set');
        }
        $variant = \App\Support\i18n\Normalize::normalizeVariant($variant);

        // Prefer MySQL/MariaDB NULL-safe equality (<=>) to simplify the WHERE
        $sel = $this->pdo->prepare(
            "SELECT resourceId
            FROM i18n_resources
            WHERE type = ?
            AND subject = ?
            AND variant <=> ?"
        );
        $sel->execute([$type, $subject, $variant]);
        $id = (int) ($sel->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        // Race-safe insert; IGNORE + re-select works but needs a UNIQUE key
        $ins = $this->pdo->prepare(
            "INSERT IGNORE INTO i18n_resources (type, subject, variant, description)
            VALUES (?, ?, ?, NULL)"
        );
        $ins->execute([$type, $subject, $variant]);

        $newId = (int) $this->pdo->lastInsertId();
        if ($newId > 0) return $newId;

        // Re-select in case of duplicate
        $sel->execute([$type, $subject, $variant]);
        $id = (int) ($sel->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        throw new \RuntimeException("Failed to resolve resourceId for {$type}/{$subject}/" . ($variant ?? 'NULL'));
    }
    

    /** Optional “no writes” for smoke tests */
    public function setDryRun(bool $dry): void
    {
        $this->dryRun = $dry;
    }

    
    /**
     * Try to obtain a PDO without DI.
     * Order:
     *  1) Existing $this->db (if class has it) via getPdo()/pdo()
     *  2) Construct DatabaseService and take its PDO
     *  3) Build PDO from Config keys (db.*)
     */
   private function resolvePdo(): ?PDO
    {
        // 1) Existing $this->db (optional)
        try {
            if (property_exists($this, 'db') && $this->db) {
                $db = $this->db;
                $this->pdo = method_exists($db, 'getPdo')
                    ? $db->getPdo()
                    : (method_exists($db, 'pdo') ? $db->pdo() : null);
                if ($this->pdo instanceof PDO) {
                    //LoggerService::logDebugI18n('TQP.resolvePdo', ['via' => 'this->db']);
                    return $this->pdo;
                }
            }
        } catch (\Throwable $e) {
            LoggerService::logDebugI18n('TQP.resolvePdo.dbPropFail', ['msg' => $e->getMessage()]);
        }

       // 2) New DatabaseService()
        try {
            if (class_exists(DatabaseService::class)) {
                $svc = new DatabaseService();
                $this->pdo = method_exists($svc, 'getPdo')
                    ? $svc->getPdo()
                    : (method_exists($svc, 'pdo') ? $svc->pdo() : null);
                if ($this->pdo instanceof PDO) {
                    LoggerService::logDebugI18n('TQP.resolvePdo', ['via' => 'DatabaseService()']);
                    return $this->pdo;
                }
            }
        } catch (\Throwable $e) {
            LoggerService::logDebugI18n('TQP.resolvePdo.svcFail', ['msg' => $e->getMessage()]);
        }

        // 3) Build from Config
        try {
            $dsn = Config::get('db.dsn');
            if (!$dsn) {
                $host = (string)(Config::get('db.host') ?? 'localhost');
                $name = (string)(Config::get('db.name') ?? '');
                $port = (string)(Config::get('db.port') ?? '3306');
                $charset = 'utf8mb4';
                if ($name !== '') {
                    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
                }
            }
            if ($dsn) {
                $user = (string)(Config::get('db.user') ?? Config::get('db.username') ?? '');
                $pass = (string)(Config::get('db.pass') ?? Config::get('db.password') ?? '');
                $this->pdo = new PDO(
                    $dsn,
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
                LoggerService::logDebugI18n('TQP.resolvePdo', ['via' => 'Config']);
                return $this->pdo;
            }
        } catch (\Throwable $e) {
            LoggerService::logDebugI18n('TQP.resolvePdo.cfgFail', ['msg' => $e->getMessage()]);
        }

        return null;
    }

    public function setScopeFilters(array $filters): void
    {
        // Keep only non-empty strings; prevents malformed SQL.
        $out = [];
        foreach ($filters as $k => $v) {
            if ($v !== null && $v !== '') { $out[$k] = (string)$v; }
        }
        $this->scopeFilters = $out;
    }

    /** Helpful for debugging/testing */
    public function getScopeFilters(): array
    {
        return $this->scopeFilters;
    }


}


