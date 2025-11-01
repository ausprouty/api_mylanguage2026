<?php

declare(strict_types=1);

namespace App\Security;

use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use PDO;


final class CronTokenGuard
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
            return $t;
        }
        $hdr = $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
        return is_string($hdr) ? trim($hdr) : '';
    }

    /**
     * One-time token auth backed by cron_tokens(token PK).
     * Deletes the row to enforce single use.
     */
    public function authorizeOnce(string $token): bool
    {
        if ($token === '') {
            LoggerService::logError('cron.token.auth.fail', 
             [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'message'  => 'cron.token.auth.fail',
                'token'    => $token,
                'message' => 'token is blank'
            ]);
            return false;
        }
        // (Optional) fast reject on clearly invalid format (tweak as needed)
        if (!preg_match('/^[0-9a-f]{64}$/i', $token)) {
            LoggerService::logError ('cron.token.auth.fail',  [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'message'  => 'token does not match pattern',
            
            ]);
             return false;
         }
          LoggerService::logError ('check token',  [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'token'  => $token,
            
            ]);
        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare(
            'DELETE FROM cron_tokens WHERE token = :t LIMIT 1'
        );
        LoggerService::logError ('deleted token',  [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'token'  => $token,
            
            ]);
        $stmt->execute([':t' => $token]);
        $ok = $stmt->rowCount() === 1;
        if (!$ok) {
            LoggerService::logError('cron.token.auth.fail', 
             [
                'method'   => __METHOD__ ,
                'function' => __FUNCTION__ ,
                'line'     => __LINE__ ,
                'message'  => 'cron.token.auth.fail',
                'token'    => $token,
                'token.len' => strlen($token)
            ]);
        }
        return $ok;
    }

}   
