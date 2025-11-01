<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Configuration\Config;
use App\Cron\TranslationQueueProcessor;
use App\Security\CronTokenGuard;
use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use PDO;

final class TranslationQueueController
{
    public function __construct(
        private DatabaseService $db,
        private LoggerService $log,
        private TranslationQueueProcessor $processor,
        private CronTokenGuard $guard,
    ) {}

    /**
     * Run the queue once if authorized.
     * Accepts token via route {token} or X-CRON-TOKEN header.
     */
    public function __invoke(array $args = []): void
    {
        $token = $this->guard->extractToken($args);
        if (!$this->guard->authorizeOnce($token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'forbidden']);
            return;
        }
        $this->processor->runOnce();
        echo json_encode(['ok' => true]);
    }

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

     
    
}
