<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Configuration\Config;
use App\Cron\TranslationQueueProcessor;
use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use PDO;

final class TranslationQueueController
{
    public function __construct(
        private DatabaseService $db,
        private LoggerService $log,
        private TranslationQueueProcessor $processor
    ) {}

    /**
     * HTTP entrypoint: /cron/{token}?batches=1&sleepMs=0
     * Route calls: [$controller, 'run']
     *
     * @param array<string,mixed> $args  expects ['token'=>string]
     */
    public function run(array $args = []): void
    {
        header('Content-Type: application/json');

        LoggerService::logDebug('TranslationQueueContoller-30', 'running');

        $batches = (int)($_GET['batches'] ?? 1);
        if ($batches < 1) { $batches = 1; }
        if ($batches > 20) { $batches = 20; } // safety cap

        $sleepMs = (int)($_GET['sleepMs'] ?? 0);
        if ($sleepMs < 0) { $sleepMs = 0; }
        if ($sleepMs > 5000) { $sleepMs = 5000; }

        // Serialize with DB advisory lock (MariaDB/MySQL)
        $lockName = 'tq:translation-queue';
        $locked = $this->acquireDbLock($this->db->getPdo(), $lockName, 0);

        if (!$locked) {
            http_response_code(423);
            echo json_encode(['ok' => false, 'err' => 'busy']);
            return;
        }

        $ran = 0;
        $t0 = microtime(true);

        try {
            for ($i = 0; $i < $batches; $i++) {
                $this->processor->runOnce();
                $ran++;
                LoggerService::logDebug('TranslationQueueContoller-57', $ran);
                if ($sleepMs > 0 && $i + 1 < $batches) {
                    usleep($sleepMs * 1000);
                }
            }
        } finally {
            $this->releaseDbLock($this->db->getPdo(), $lockName);
        }

        $elapsedMs = (int)((microtime(true) - $t0) * 1000);
        echo json_encode([
            'ok'      => true,
            'batches' => $ran,
            'ms'      => $elapsedMs
        ]);
    }

    /** Constant-time compare. */
    private function safeEquals(string $a, string $b): bool
    {
        if ($a === '' || $b === '') return false;
        return hash_equals($a, $b);
    }

    private function acquireDbLock(PDO $pdo, string $name, int $waitSec): bool
    {
        $stmt = $pdo->prepare('SELECT GET_LOCK(:n, :t) AS ok');
        $stmt->execute([':n' => $name, ':t' => $waitSec]);
        $val = (int)($stmt->fetchColumn() ?: 0);
        return $val === 1;
    }

    private function releaseDbLock(PDO $pdo, string $name): void
    {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:n)');
        $stmt->execute([':n' => $name]);
    }

        /**
     * Authorization model:
     *  - CLI: always allowed
     *  - HTTP: must present a one-time token that exists in cron_tokens
     *          and is deleted atomically on use (prevents replay).
     *
     * Token can be provided via:
     *   - $args['token']  (path param)
     *   - $_GET['token'] or $_GET['t'] (query)
     *   - HTTP header 'X-Cron-Token'
     *
     * Optional (uncomment if your table has these columns):
     *   - scope = 'translation'
     *   - expires_at IS NULL OR expires_at > NOW()
     */
    private function isAuthorized(PDO $pdo, array $args): bool
    {
        LoggerService::logDebug('TranslationQueueContoller-111', $args);
        if (PHP_SAPI === 'cli') {
            return true;
        }

        $given = (string)(
            $args['token']
            ?? ($_GET['token'] ?? '')
            ?? ($_GET['t'] ?? '')
        );

        if ($given === '' && isset($_SERVER['HTTP_X_CRON_TOKEN'])) {
            $given = (string)$_SERVER['HTTP_X_CRON_TOKEN'];
        }

        if ($given === '') {
            return false;
        }
        LoggerService::logDebug('TranslationQueueContoller-129', $given);

        // One-time use: delete the row if present (atomic gate)
        // Minimal schema: cron_tokens(token VARCHAR PRIMARY KEY)
        $sql = 'DELETE FROM cron_tokens
                WHERE token = :t
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':t' => $given]);
        return $stmt->rowCount() === 1;
    }
}
