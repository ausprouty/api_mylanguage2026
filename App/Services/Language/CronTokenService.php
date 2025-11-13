<?php

declare(strict_types=1);

namespace App\Services\Language;

use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use PDO;


final class CronTokenService
{
    public function __construct(
        private DatabaseService $db,
        private LoggerService $log,
    ) {}

    /** Return a token from args or headers; '' if none. */
   public function extractToken(array $args): string
   {
        $t = (string)($args['token'] ?? '');
        if ($t !== '') {
            LoggerService::logDebugCronToken('cron.token', 
             [
                'message'  => 'token in args',
                'token'    => $t

            ]);
            return $t;
        }
        $hdr = $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
        LoggerService::logDebugCronToken('cron.token', 
             [
                'message'  => 'token in HTTP_X_CRON_TOKEN',
                'token'    => trim($hdr)

            ]);
        return is_string($hdr) ? trim($hdr) : '';
    }

    /**
     * One-time token auth backed by cron_tokens(token PK).
     * Deletes the row to enforce single use.
     */
    public function authorizeOnce(string $token): bool
    {
        if ($token === '') {
            LoggerService::logDebugCronToken('CTS.auth.fail', 
             [
                'message' => 'token is blank'
            ]);
            return false;
        }
        // (Optional) fast reject on clearly invalid format (tweak as needed)
        if (!preg_match('/^[0-9a-f]{32}$/i', $token)) {
            LoggerService::logDebugCronToken ('CTS.auth.fail',  [
                'message'  => 'token does not valid pattern for 32',
                'token'    =>  $token,
            
            ]);
             return false;
         }
        LoggerService::logDebugCronToken('cron.token.delete',  [
            'token'  => $token,
        
        ]);
        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare(
            'DELETE FROM cron_tokens WHERE token = :t LIMIT 1'
        );
       
        $stmt->execute([':t' => $token]);
        
        $ok = $stmt->rowCount() === 1;
        if (!$ok) {
            LoggerService::logDebugCronToken('CTS.delete.fail', 
             [
                'message'  => 'cron.token.delete.fail',
                'token'    => $token,
                'token.len' => strlen($token)
            ]);
        }
        return $ok;
    }

    /**
     * Create a one-time token in cron_tokens. Returns the token string or null.
     * Schema expected:
     *   cron_tokens(id PK AUTO_INCREMENT, token VARCHAR(64) UNIQUE, created_at TIMESTAMP)
     */
    public function issueCronKey(): ?string
    {
        $token = $this->generateRandomToken(16); // 32 hex chars
        LoggerService::logDebugCronToken('CTS.token.new', 
             [
                'message'  => 'cron.token.new',
                'token'    => $token,
                'token.len' => strlen($token)
        ]);
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO cron_tokens (token) VALUES (:t)"
            );
            $stmt->execute([':t' => $token]);
           return $token;
        } catch (\Throwable $e) {
            LoggerService::logDebugCronToken('CTS.error', [
                'err' => $e->getMessage(),
                ]);
            return null;
        }
    }

    /** Generate a cryptographically random hex token of $bytes bytes. */
    private function generateRandomToken(int $bytes = 16): string
    {
        return bin2hex(random_bytes(max(8, $bytes)));
    }

}   

