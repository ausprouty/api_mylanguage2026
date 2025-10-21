<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\LoggerService;
use App\Configuration\Config;
use PDO;

final class I18nStringsRepository
{
    public function __construct(private PDO $pdo) {}
  

    /**
     * Ensure each (clientId, resourceId, keyHash) exists in i18n_strings with englishText,
     * then return:
     *   - $stringMap keyed by: dot key, "sha1:<hex>", and bare "<hex>"
     *   - $stringIds numeric list used to fetch translations
     *
     * $masters rows look like: ['key' => 'interface.nextVideo', 'text' => 'Next Video']
     *
     * @return array{0: array<string,int>, 1: int[]}
     */
    public function ensureAndMapForMasters(
        int $clientId,
        int $resourceId,
        array $masters
    ): array {
        // set Debugging
        $dbg= Config::get('logging.i18n_debug', false);
        
        $dbg = true;
        if ($dbg){
            Log::logInfo('I18nStrings-33', 'masters', $masters);
        }
        // 1) Build unique set of (hash => englishText)
        $hashToText = [];
        foreach ($masters as $m) {
            $txt = (string)($m['text'] ?? '');
            if ($txt === '') { continue; }
            $hashToText[sha1($txt)] = $txt;
        }
        if (empty($hashToText)) {
            return [[], []];
        }

        // 2) Insert any missing/changed strings (idempotent via UNIQUE (clientId,resourceId,keyHash))
       
         if ($dbg){
            Log::logInfo('I18nStrings-49', 'hashToText', $hashToText);
            Log::logInfo('I18nStrings-50', 'clientId', $clientId);
            Log::logInfo('I18nStrings-51', 'resourceId', $resourceId);
        }       
        $ins = $this->pdo->prepare(
            "INSERT INTO i18n_strings
                (clientId, resourceId, keyHash, englishText, createdAt, updatedAt)
             VALUES
                (:c, :r, :h, :t, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                englishText = VALUES(englishText),
                updatedAt   = UTC_TIMESTAMP()"
        );
        foreach ($hashToText as $h => $t) {
            if ($dbg){
            Log::logInfo('I18nStrings-64', 'hash', $h);
            Log::logInfo('I18nStrings-65', 'text', $t);

           } 
            $ins->bindValue(':c', $clientId,  PDO::PARAM_INT);
            $ins->bindValue(':r', $resourceId, PDO::PARAM_INT);
            $ins->bindValue(':h', $h);
            $ins->bindValue(':t', $t);
            $ins->execute();
        }

        // 3) Read mapping hash -> stringId
        [$in, $params] = $this->buildInParams(array_keys($hashToText), 'h');
        $sel = $this->pdo->prepare(
            "SELECT stringId, keyHash
               FROM i18n_strings
              WHERE clientId = :c AND resourceId = :r AND keyHash IN ($in)"
        );
        $sel->bindValue(':c', $clientId,  PDO::PARAM_INT);
        $sel->bindValue(':r', $resourceId, PDO::PARAM_INT);
        foreach ($params as $k => $v) { $sel->bindValue($k, $v); }
        $sel->execute();

        $hashToId = [];
        while ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
            $hashToId[(string)$row['keyHash']] = (int)$row['stringId'];
        }

        // 4) Build stringMap: dot / "sha1:<hex>" / bare hex -> stringId
        $stringMap = [];
        foreach ($masters as $m) {
            $dot = (string)($m['key']  ?? '');
            $txt = (string)($m['text'] ?? '');
            if ($txt === '') { continue; }
            $hex = sha1($txt);
            $sid = $hashToId[$hex] ?? null;
            if ($sid) {
                if ($dot !== '')        { $stringMap[$dot]      = $sid; }
                $stringMap["sha1:$hex"] = $sid;
                $stringMap[$hex]        = $sid;
            }
        }

        // 5) Unique numeric list
        $stringIds = array_values(array_unique(array_values($stringMap)));

        return [$stringMap, $stringIds];
    }

    /** Utility: build a named-params IN(...) list */
    private function buildInParams(array $vals, string $prefix = 'p'): array
    {
        $params = []; $ph = []; $i = 0;
        foreach ($vals as $v) {
            $k = ':' . $prefix . $i++;
            $ph[] = $k;
            $params[$k] = (string)$v;
        }
        return [implode(',', $ph), $params];
    }
}
