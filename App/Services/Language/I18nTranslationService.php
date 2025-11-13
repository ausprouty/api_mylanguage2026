<?php

declare(strict_types=1);

namespace App\Services\Language;

use App\Configuration\Config;
use App\Contracts\Translation\TranslationService as TranslationServiceContract;
use App\Repositories\I18nStringsRepository;       // kept for compatibility (not strictly required)
use App\Repositories\I18nTranslationsRepository;
use App\Repositories\I18nClientsRepository;
use App\Repositories\I18nResourcesRepository;
use App\Repositories\LanguageRepository;
use App\Services\Language\CronTokenService;
use App\Services\Database\DatabaseService;
use App\Services\LoggerService;
use App\Support\Async;
use App\Support\i18n\ExcludeKeyMatcher;
use App\Support\i18n\Normalize;


class I18nTranslationService implements TranslationServiceContract
{
    public function __construct(
        private CronTokenService           $cronTokenService,
        private DatabaseService            $db,
        private I18nStringsRepository      $strings,      // not required by this implementation but left for DI BC
        private I18nTranslationsRepository $translations,
        private I18nClientsRepository      $clients,
        private I18nResourcesRepository    $resources,
      
        private LanguageRepository         $languages,
        private string                     $baseLanguage = 'eng00'

    ) {}

    public function baseLanguage(): string
    {
        return $this->baseLanguage;
    }

    /**
     * Google-only translation flow.
     * Ensures masters exist in i18n_strings, then translates/queues.
     */
    public function translateBundle(
        array $bundle,
        string $languageCodeHL,
        ?string $variant,
        array $ctx = []
    ): array {
        
    // ---- context -----------------------------------------------------
        $kind                = (string)($ctx['kind'] ?? 'interface');
        $resourceSubject     = (string)($ctx['resourceSubject'] ?? 'app');
        $ctxVariant          = (string)($ctx['resourceVariant'] ?? '');
        $clientCode          = (string)($ctx['clientCode'] ?? 'wsu');
        $normVariant         = (string)($ctx['variant'] ?? ($variant ?: 'default'));
        $isBase              = (bool)($ctx['isBase']
                                 ?? ($languageCodeHL === $this->baseLanguage));

       
        // Choose type by kind
        $resourceType = ($kind === 'interface') ? 'interface' : 'commonContent';

        // Single source of truth for variant
        $variantCode = $this->normalizeVariant(
            $ctx['resourceVariant'] ?? null,
            $ctx['variant']         ?? null,
            $variant                ?? null
        ); 
        // returns null for "", "default", or null
         // Later, for meta:
        $variantForMeta = $variantCode ?? 'default';

        // Client to store under
        $clientCodeForStorage = ($resourceType === 'commonContent') ? 'global' : $clientCode;

        // Ensure FK parents exist
        $clientId = $this->clients->ensureIdByCode(
            $clientCodeForStorage,
            $variantCode,
            $clientCodeForStorage . ' (auto)',
            1
        );
        $resourceId = $this->resources->ensureIdByTypeSubjectVariant(
            $resourceType,
            $resourceSubject,
            $variantCode,
            sprintf('%s %s %s (auto)',
                $resourceType,
                $resourceSubject,
                $variantCode ?? 'default'
            )
        );

     
        LoggerService::logDebugI18n('I18nT.ctxids', [
            'isBase'        => $isBase,
            'kind'          => $kind,
            'type'          => $resourceType,
            'subject'       => $resourceSubject,
            'variant'       => ($variantCode ?? 'default'),
            'clientCode'    => $clientCode,
            'clientId'      => $clientId,
            'resourceId'    => $resourceId,
            'languageCodeHL'=> $languageCodeHL,
        ]);
        // Choose which client to use when writing i18n_strings
        $clientCodeForStorage = ($resourceType === 'commonContent') ? 'global' : $clientCode;

        // Make sure the client & resource exist (no 1452, no 1451)
        $clientId = $this->clients->ensureIdByCode(
            $clientCodeForStorage,
            $variantForMeta,
            sprintf('%s (auto)', $clientCodeForStorage)
        );

        $resourceId = $this->resources->ensureIdByTypeSubjectVariant(
            $resourceType,
            $resourceSubject,
            $variantForMeta,
            sprintf('%s %s %s (auto)',
                $resourceType,
                $resourceSubject,
                $variantForMeta ?? 'default'
            )
        );


        LoggerService::logDebugI18n('I18nTr-ctx', [
            'resourceType'   => $resourceType,
            'resourceSubj'   => $resourceSubject,
            'variantForMeta' => $variantForMeta,
            'clientCode'     => $clientCodeForStorage,
            'clientId'       => $clientId,
            'resourceId'     => $resourceId,
        ]);

        // ---- excludes (bundle + .env/config) --------------------------------
        $defaults   = (array) Config::get('i18n.exclude_keys_default', []);
        $bundleEx   = (array) ($bundle['meta']['i18n']['excludeKeys'] ?? []);
        $excludeAll = array_values(array_unique(array_filter(
            array_map('trim', array_merge($defaults, $bundleEx))
        )));

        // ---- extract masters (respects exclude list) ------------------------
 
        LoggerService::logDebugI18n('I18nT.bundle (pre-extract)', [
            'exclude'  => $excludeAll
        ]);

        $masters = $this->extractMasterTexts($bundle, $excludeAll);
        
        LoggerService::logDebugI18n('I18nTmasters (raw)', [
            'masters'  => $masters
        ]);
       
         
        // ---- ensure masters exist in i18n_strings, then build map+ids -------
        [$stringMap, $stringIds] = $this->ensureMastersAndMap(
            $clientId,
            $resourceId,
            $masters,
            $dbg = false
        );
       
        LoggerService::logDebugI18n('I18nT.strngMap.keys.sample',
        [
            'keys'      =>  array_slice(array_keys($stringMap), 0, 10),
            'stringIds' =>  array_slice($stringIds, 0, 10)
        ]);
   
       

        // Base language short-circuit: keep English, but we keep the catalog in sync.
        if ($isBase) {
            return $this->withMeta($bundle, [
                'resourceSubject'      => $resourceSubject,
                'resourceVariant'      => $variantCode,
                'clientCode'           => $clientCode,
                'languageCodeHL'       => $languageCodeHL,
                'languageCodeGoogle'   => 'en',
                'variant'              => ($variantCode ?? 'default'),
                'keysTotal'            => count($stringIds),
                'keysMissing'          => 0,
                'keysFuzzy'            => $bundle['meta']['keysFuzzy'] ?? 0,
                'translationComplete'  => true,
            ]);
        }

        // ---- resolve Google code from HL; set to 'en' if not found ------------------------------
        $languageCodeGoogle = $this->languages
            ->getCodeGoogleFromCodeHL($languageCodeHL) ?? '';
        if ($languageCodeGoogle === '') {
            LoggerService::logError('I18nTr.google', [
                'languageCodeHL'   => $languageCodeHL,
                'message'          => 'no valid languageCodeGoogle',
               
            ]);
            $languageCodeGoogle = 'en';
        }
        if (empty($stringIds)) {
            return $this->withMeta($bundle, [
                'resourceSubject'      => $resourceSubject,
                'resourceVariant'      => ($variantCode ?? 'default'),
                'clientCode'           => $clientCode,
                'languageCodeHL'       => $languageCodeHL,
                'languageCodeGoogle'   => $languageCodeGoogle,
                'variant'              => $normVariant,
                'keysTotal'            => 0,
                'keysMissing'          => 0,
                'keysFuzzy'            => $bundle['meta']['keysFuzzy'] ?? 0,
                'translationComplete'  => true,
            ]);
        }

        LoggerService::logDebugI18n('I18nT.stringIds', [
                'stingId'  => $stringIds
        ]); 

        // ---- fetch translations (Google-only) -------------------------------
         $rowsGoogle = $this->translations
            ->fetchByStringIdsAndLanguageGoogle($stringIds, $languageCodeGoogle);
        LoggerService::logDebugI18n('I18nT.rowsGoogle',[
            'rows'     => $rowsGoogle
        ]);

        $trById = [];
        foreach ($rowsGoogle as $r) {
            $sid = (int)($r['stringId'] ?? 0);
            if ($sid > 0) { $trById[$sid] = (string)($r['translatedText'] ?? ''); }
        }

        // ---- count translated vs missing; enqueue missing -------------------
        $keysTotal     = count($stringIds);
        $translatedCnt = 0;
        $missingRows   = [];

        $norm = static function (?string $s): string {
            if ($s === null) return '';
            $s = preg_replace('/\s+/u', ' ', trim($s));
            return \function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        };

        foreach ($masters as $m) {
            $dot = (string)($m['key']  ?? '');
            $txt = (string)($m['text'] ?? '');
            if ($txt === '') { continue; }

            $shaHex = sha1($txt);
            $shaKey = 'sha1:' . $shaHex;

            $sid = $stringMap[$dot] ?? $stringMap[$shaKey] ?? $stringMap[$shaHex] ?? null;
            $sid = ($sid !== null) ? (int)$sid : null;

            $applied = ($sid !== null && isset($trById[$sid])) ? (string)$trById[$sid] : '';
            $isTranslated = ($applied !== '') && ($norm($applied) !== $norm($txt));

            if ($isTranslated) {
                $translatedCnt++;
            } else {
                // missing or English fallback → enqueue
                $missingRows[] = [
                    'stringKey' => ($dot !== '' ? $dot : $shaKey),
                    'keyHash'   => $shaHex,
                    'sid'       => $sid,
                    'text'      => $txt,
                ];
            }
        }

        $keysMissing = max(0, $keysTotal - $translatedCnt);

        if (!empty($missingRows)) {
            foreach ($missingRows as $mr) {
                $this->enqueueMissing(
                    clientCode:            $clientCode,
                    resourceType:          $resourceType,  
                    subject:               $resourceSubject,
                    variantCode:           $variant ?? 'default',
                    stringKey:             $mr['stringKey'],
                    sourceKeyHash:         $mr['keyHash'],
                    sourceStringId:        $mr['sid'],
                    sourceLanguageGoogle:  'en',
                    targetLanguageGoogle:  $languageCodeGoogle,
                    sourceText:            $mr['text'],
                    priority:              0
                );
            }
            // Optionally kick a worker based on environment.
            // In production we usually rely on cron. You can force a kick with
            // FEATURE_KICK_QUEUE=1.
            $this->kickQueueWorker(
                client:   $clientCode,
                type:     $resourceType, 
                subject:  $resourceSubject,
                variant:  $variantCode ?? 'default',
                lang:     $languageCodeGoogle
            );
        }
            

        // ---- apply translations back onto the bundle ------------------------
        $out = $this->applyTranslationsByStringId(
            $bundle,
            $stringMap,
            $trById,
            $excludeAll
        );      
      
        // If there are still missing keys, issue a one-time cron token so the client
        // can trigger the next background translation chunk.
        
         // English is out base so it can never have items missing.
        if ($languageCodeGoogle == 'en'){
            $keysMissing = 0;
        }
        $cronToken = null;
        if ($keysMissing > 0) {
            $cronToken = $this->cronTokenService->issueCronKey();
        }
        LoggerService::logDebugCronToken('ITS.cronKey', [
            'token' => $cronToken ,
        ]);
       
        $out = $this->withMeta($out, [
            'resourceSubject'      => $resourceSubject,
            'resourceVariant'      => $variantForMeta,
            'clientCode'           => $clientCode,
            'languageName'         => $this->languages->getEnglishNameForLanguageCodeHL($languageCodeHL),
            'languageCodeHL'       => $languageCodeHL,
            'languageCodeISO'      =>  $this->languages->getCodeIsoFromCodeHL($languageCodeHL),
            'languageCodeGoogle'   => $languageCodeGoogle,
            'variant'              => $normVariant,
            'keysTotal'            => $keysTotal,
            'keysMissing'          => $keysMissing,
            'keysFuzzy'            => $out['meta']['keysFuzzy'] ?? 0,
            'translationComplete'  => ($keysMissing === 0),
            'cronKey'              => $cronToken,
            'fallbackCount'        => $keysMissing,
            'edited'               => 'tues pm'
        ]);

        return $out;
    }

    // ---------------------------------------------------------------------
    // internals (ordering & neatness)
    // ---------------------------------------------------------------------

    /**
     * Ensure every master line exists in i18n_strings (by clientId, resourceId, keyHash),
     * then return [$stringMap, $stringIds].
     *
     * $stringMap is keyed by:
     *   - dot key (e.g. "interface.nextVideo")
     *   - "sha1:<hex>"
     *   - "<hex>"
     */
    private function ensureMastersAndMap(
        int $clientId,
        int $resourceId,
        array $masters,
        bool $dbg = false
    ): array {
        // 1) Build unique set of (keyHash => englishText) from the bundle
        $hashToText = [];
        foreach ($masters as $m) {
            $txt = (string)($m['text'] ?? '');
            if ($txt === '') { continue; }
            $hashToText[sha1($txt)] = $txt;
        }
        if (!$hashToText) {
            return [[], []];
        }

        LoggerService::logDebugI18n('I18nT.hashToText', [
            'hash'     => $hashToText
        ]); 


        // 2) Read existing rows for these hashes (scope by clientId/resourceId)
        [$in, $params] = $this->buildInParams(array_keys($hashToText), 'h');
        LoggerService::logDebugI18n('I18nT.in',[
            'in'     => $in
        ]); 

        $sel = $this->db->prepare(
            "SELECT stringId, keyHash, englishText
            FROM i18n_strings
            WHERE clientId = :c AND resourceId = :r AND keyHash IN ($in)"
        );
        $sel->execute(array_merge(
            ['c' => $clientId, 'r' => $resourceId],
            $params
        ));
       LoggerService::logDebugI18n('I18nT.params', [
            'params'   => $params
        ]);
        $haveId   = []; // keyHash => stringId
        $haveText = []; // keyHash => englishText
        while ($row = $sel->fetch(\PDO::FETCH_ASSOC)) {
            $kh = (string)$row['keyHash'];
            $haveId[$kh]   = (int)$row['stringId'];
            $haveText[$kh] = (string)$row['englishText'];
        }
        LoggerService::logDebugI18n('I18nT.params', [
            'params'   => $params
        ]);
        // 3) Insert truly missing rows (no ON DUPLICATE to avoid burning AUTO_INCREMENT)
        $ins = $this->db->prepare(
            "INSERT INTO i18n_strings
                (clientId, resourceId, keyHash, englishText, createdAt, updatedAt)
             SELECT :c_ins, :r_ins, :h_ins, :t_ins,
                    UTC_TIMESTAMP(), UTC_TIMESTAMP()
               FROM DUAL
              WHERE NOT EXISTS (
                    SELECT 1
                      FROM i18n_strings
                     WHERE clientId = :c_chk
                       AND resourceId = :r_chk
                       AND keyHash = :h_chk
              )"
        );
        LoggerService::logDebugI18n('I18nT.params', [
            'params'   => $params
        ]);
        foreach ($hashToText as $h => $t) {
            if (isset($haveId[$h])) { continue; }
            try {
                $ins->execute([
                    'c_ins' => $clientId,
                    'r_ins' => $resourceId,
                    'h_ins' => $h,
                    't_ins' => $t,
                    'c_chk' => $clientId,
                    'r_chk' => $resourceId,
                    'h_chk' => $h,
                ]);
              
            } catch (\Throwable $e) {
                // If two requests race, a duplicate can still occur; ignore that only.
                $msg = (string)$e->getMessage();
                if (stripos($msg, 'duplicate') === false && stripos($msg, '1062') === false) {
                    throw $e;
                }
            }
        }
        LoggerService::logDebugI18n('I18nT.resourceId', [
            'resourceId' => $resourceId
        ]);
        // 4) Update text only if it changed (no id burn)
        $upd = $this->db->prepare(
            "UPDATE i18n_strings
                SET englishText = :t_set, updatedAt = UTC_TIMESTAMP()
              WHERE clientId = :c
                AND resourceId = :r
                AND keyHash = :h
                AND englishText <> :t_cmp"
        );
        foreach ($hashToText as $h => $t) {
            if (!isset($haveId[$h])) { continue; } // just inserted; skip redundant update
            if (isset($haveText[$h]) && $haveText[$h] === $t) { continue; }
             $upd->execute([
                'c'     => $clientId,
                'r'     => $resourceId,
                'h'     => $h,
                't_set' => $t,
                't_cmp' => $t,
            ]);
        }

        // 5) Re-select mapping to pick up any newly inserted ids
        $sel2 = $this->db->prepare(
            "SELECT stringId, keyHash
            FROM i18n_strings
            WHERE clientId = :c AND resourceId = :r AND keyHash IN ($in)"
        );
        $sel2->execute([':c' => $clientId, ':r' => $resourceId] + $params);

        $hashToId = [];
        while ($row = $sel2->fetch(\PDO::FETCH_ASSOC)) {
            $hashToId[(string)$row['keyHash']] = (int)$row['stringId'];
        }

        // 6) Build stringMap (dot key + sha1 forms) and the numeric id list
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
        $stringIds = array_values(array_unique(array_values($stringMap)));

        LoggerService::logDebugI18n('I18nT.ensure', [
            'masters'   => count($masters),
            'hashes'    => count($hashToText),
            'mapped'    => count($stringIds),
            'sampleKeys'=> array_slice(array_keys($stringMap), 0, 8),
        ]);
    

        return [$stringMap, $stringIds];
    }


    /** Walk the bundle and return master lines with stable dot-keys. */
    private function extractMasterTexts(
        array $bundle,
        array $excludeKeys = []
    ): array
    {
        $rows = [];
        $matcher = new ExcludeKeyMatcher($excludeKeys);
        $this->collectStrings($bundle, [], ['meta'], $rows, $matcher);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'key'  => implode('.', $r['path']),
                'text' => $r['text'],
            ];
        }

        //LoggerService::logDebugI18n('I18nTranslationService-319', 'ensureMastersAndMap', [
            //    'bundle'   => $bundle,
            //    'out'    => $out,
            //]);

        return $out;
    }

    private function collectStrings(
        array $node,
        array $path,
        array $skipTopKeys,
        array &$out,
        ?ExcludeKeyMatcher $matcher = null
    ): void
   {
        if (empty($path) && is_array($node)) {
            foreach ($skipTopKeys as $skip) { unset($node[$skip]); }
        }
        if (is_array($node)) {
            foreach ($node as $k => $v) {
                $p = [...$path, (string)$k];
                $dot = implode('.', $p);
                if ($matcher && $matcher->isExcluded($dot)) {
                    // prune entire subtree
                    continue;
                }
                if (is_array($v)) {
                    $this->collectStrings($v, $p, $skipTopKeys, $out, $matcher);
                } elseif (is_string($v)) {
                    if ($this->looksHumanText($p, $v)) {
                        $out[] = ['path' => $p, 'text' => $v];
                    }
                }
            }
        }
    }

    private function looksHumanText(array $path, string $text): bool
    {
        // ignore empty/whitespace-only values
        if ($text === '' || trim($text) === '') return false;

        $last = (string)end($path);

        // Skip *technical* fields by name, but DO NOT nuke words like "video".
        // - match whole token or token-at-end with common separators
        // - case-insensitive
        $technical = [
            'id', 'ids',
            'code', 'codes',
            'uuid', 'guid',
            'languagecode', 'clientid', 'resourceid', 'stringid',
        ];

        $lastNorm = strtolower($last);

        // exact match (e.g., "id", "code")
        if (in_array($lastNorm, $technical, true)) return false;

        // suffix token (e.g., "clientId", "resource_id", "language-code")
        if (preg_match('/(^|[._-])(id|ids|code|codes|uuid|guid|languagecode|clientid|resourceid|stringid)$/i', $last)) {
            return false;
        }

        return true;
    }


    private function setByPath(array &$arr, array $path, string $value): void
    {
        $ref =& $arr;
        $n = count($path);
        for ($i = 0; $i < $n - 1; $i++) {
            $k = $path[$i];
            if (!isset($ref[$k]) || !is_array($ref[$k])) { $ref[$k] = []; }
            $ref =& $ref[$k];
        }
        $ref[$path[$n - 1]] = $value;
    }

    /** Apply translations onto the bundle using stringId mapping. */
    private function applyTranslationsByStringId(
        array $bundle,
        array $stringMap,
        array $trById,
        array $excludeKeys = []
    ): array {
        $out = $bundle;

        // stableKey => translated
        $keyToText = [];
        foreach ($stringMap as $stableKey => $sid) {
            $sid = (int)$sid;
            if (isset($trById[$sid]) && is_string($trById[$sid])) {
                $keyToText[(string)$stableKey] = $trById[$sid];
            }
        }
        if (empty($keyToText)) { return $out; }

        $rows = [];
        $matcher = new ExcludeKeyMatcher($excludeKeys);
        $this->collectStrings($bundle, [], ['meta'], $rows, $matcher);

        foreach ($rows as $r) {
            $path = (array)($r['path'] ?? []);
            $text = (string)($r['text'] ?? '');
            if ($text === '') { continue; }

            $dot    = implode('.', $path);
            if ($matcher->isExcluded($dot)) { continue; }
            $shaHex = sha1($text);
            $shaKey = 'sha1:' . $shaHex;

            $new = $keyToText[$dot]
                ?? $keyToText[$shaKey]
                ?? $keyToText[$shaHex]
                ?? null;

            if ($new !== null) {
                $this->setByPath($out, $path, $new);
            }
        }

        return $out;
    }

    /** Insert/refresh queue rows for missing translations (Google-only). */
    private function enqueueMissing(
        string $clientCode,
        string $resourceType,
        string $subject,
        string $variantCode,
        string $stringKey,
        string $sourceKeyHash,
        ?int   $sourceStringId,
        string $sourceLanguageGoogle,
        string $targetLanguageGoogle,
        string $sourceText,
        int    $priority = 0
    ): void {
        if ($targetLanguageGoogle == 'en'){
            // we don't translate English
            LoggerService::logDebugI18n ('ITS.eng', [
                'clientCode'    => $clientCode,
                'resourceType'  => $resourceType,
                'subject'       => $subject,
                'variant'       => $variantCode,
                'message'       => 'targeLanguageGoogle is en'
            ]);

            return;
        }
        $sql = "
            INSERT INTO i18n_translation_queue
              (sourceStringId, sourceLanguageCodeGoogle, clientCode,
               resourceType, subject, variant, stringKey, sourceKeyHash,
               targetLanguageCodeGoogle, sourceText, status, runAfter, priority)
            VALUES
              (:sid, :srcG, :client, :rtype, :subj, :var, :skey, :shash,
               :tG, :stext, 'queued', UTC_TIMESTAMP(), :prio)
            ON DUPLICATE KEY UPDATE
              runAfter = LEAST(i18n_translation_queue.runAfter, VALUES(runAfter)),
              priority = LEAST(i18n_translation_queue.priority, VALUES(priority)),
              status   = IF(status='processing','queued',status),
              lockedBy = IF(status='processing',NULL,lockedBy),
              lockedAt = IF(status='processing',NULL,lockedAt)
        ";

        $this->db->executeQuery($sql, [
            ':sid'   => $sourceStringId,
            ':srcG'  => $sourceLanguageGoogle,
            ':client'=> $clientCode,
            ':rtype' => $resourceType,
            ':subj'  => $subject,
            ':var'   => $variantCode,
            ':skey'  => $stringKey,
            ':shash' => $sourceKeyHash,
            ':tG'    => strtolower($targetLanguageGoogle),
            ':stext' => $sourceText,
            ':prio'  => $priority,
        ]);
    }

    /** Attach/override meta fields neatly. */
    private function withMeta(array $bundle, array $add): array
    {
        $out = $bundle;
        if (!isset($out['meta']) || !is_array($out['meta'])) { $out['meta'] = []; }
        foreach ($add as $k => $v) { $out['meta'][$k] = $v; }

        // Optional font lookup for HL
        if (!isset($out['meta']['font'])) {
            $font = $this->languages->getFontDataFromLanguageCodeHL($out['meta']['languageCodeHL'] ?? '');
            if ($font && $font !== 'null') { $out['meta']['font'] = $font; }
        }

        // cleanup cruft that confuses clients
        if (array_key_exists('langHL', $out['meta'])) { unset($out['meta']['langHL']); }

        return $out;
    }

    // ---- small DB helpers --------------------------------------------------

    /** Build a named-params IN(...) list. */
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
   

    /**
     * Kick a short queue run after enqueue, driven entirely by Config::get.
     * Dev/local: kick by default. Prod: no-op unless forced in config.
     */
    function kickQueueWorker(
        string $client,
        string $type,
        string $subject,
        string $variant,
        string $lang
    ): void {
        //LoggerService::logDebugI18n('kickQueueWorker-575', 'entred', [$lang]);
        // Safe getter: return $default if key missing.
        $cfg = static function (string $key, mixed $default = null): mixed {
            try { return Config::get($key); } catch (\Throwable $e) { return $default; }
        };

        // Environment comes ONLY from 'environment'
        $env   = strtolower((string) Config::get('environment'));
        $isDev = in_array($env, ['local', 'dev', 'development'], true);
        $isProd = in_array($env, ['prod', 'production', 'remote'], true);

        $force   = (bool) $cfg('i18n.force_kick', false);
        $sec     = (int)  $cfg('i18n.force_kick_seconds', 10);
        $batch   = (int)  $cfg('i18n.force_kick_batch', 25);
        $kickDev = (bool) $cfg('i18n.kick_on_enqueue_dev', true);
        $kickProd = (bool) $cfg('i18n.kick_on_enqueue_prod', false);

        $binDirCfg = $cfg('i18n.kick_bin_dir', null);
        $runnerCfg = $cfg('i18n.kick_runner', null);

        $defaultBin = realpath(__DIR__ . '/../../../bin')
            ?: (__DIR__ . '/../../../bin');
        $binDir = (is_string($binDirCfg) && $binDirCfg !== '')
            ? $binDirCfg : $defaultBin;

        $devRunner  = $binDir . DIRECTORY_SEPARATOR . 'run-translation-queue.php';
        $prodRunner = $binDir . DIRECTORY_SEPARATOR . 'translation-cron.php';

        $cliLog = \App\Configuration\Config::getDir('logs', '/logs') . '/queue-kick.log';
        $base_dir = \App\Configuration\Config::get('base_dir');

        // Optional override: 'dev' | 'prod' | '/abs/path/to/script.php'
        if (is_string($runnerCfg) && $runnerCfg !== '') {
            if ($runnerCfg === 'dev') {
                $prodRunner = $devRunner;
            } elseif ($runnerCfg === 'prod') {
                /* keep defaults */
            } elseif (substr($runnerCfg, -4) === '.php') {
                $devRunner  = $runnerCfg;
                $prodRunner = $runnerCfg;
            }
        }
        // LoggerService::logDebugI18n('kickQueueWorker-615', 'devRunner',  [$devRunner]);
        // LoggerService::logDebugI18n('kickQueueWorker-616', 'prodRunner',  [$prodRunner]);

        // Filters for the dev runner; cron runner doesn't take these.
        $filterArgs = [
            '--lang=' . $lang,
            '--client=' . $client,
            '--type=' . $type,
            '--subject=' . $subject,
            '--variant=' . $variant,
        ];

        $devArgs = array_merge(
            $filterArgs,
            ['--seconds=' . max(5, $sec), '--batch=' . max(1, $batch)]
        );

        // translation-cron.php uses different flags
        $prodArgs = [
            '--max-secs=' . max(5, $sec),
            '--batch-size=' . max(1, $batch),
        ];
      
        if ($isDev && $kickDev) {
            if (file_exists($devRunner)) {
                //LoggerService::logDebugI18n('i18nTranslationService-740', 'devArgs',  [$devArgs]);
                //LoggerService::logDebugI18n('i18nTranslationService-741', 'devRuner',  [$devRunner]);
                $pid = \App\Support\Async::php(
                    $devRunner,     // e.g., C:\ampp82\htdocs\api_mylanguage\bin\run-translation-queue.php
                    $devArgs,       // e.g., ['--max-secs=30','--batch-size=50']
                    $cliLog,        // e.g., C:\...logs\queue-dev.log
                    $base_dir       // working dir (optional)
                );
                // optional: log PID if returned
                if ($pid) { LoggerService::logInfo('i18nTranslationService-748', ['pid' => $pid]); }
                //LoggerService::logInfo('i18nTranslationService-749', 'Async Finished');
            }
            return;
        }

        if ($isProd && ($force || $kickProd)) {
            if (file_exists($devRunner)) {
                 Async::php(
                    $devRunner,
                    $devArgs,
                    $cliLog,
                    $base_dir // optional but nice
                );
                return;
            }
            if (file_exists($prodRunner)) {
                 Async::php(
                    $prodRunner,
                    $prodArgs,
                    $cliLog,
                    $base_dir // optional but nice
                );
            }
            return;
        }

        // Default in prod: rely on cron; no-op here.
    }
    
    

    /**
     * Map "default", "", null to NULL; otherwise trimmed lowercase code.
     */
    private function normalizeVariant(?string ...$candidates): ?string
    {
        foreach ($candidates as $v) {
            if ($v === null) { continue; }
            $v = trim((string)$v);
            if ($v === '' || strcasecmp($v, 'default') === 0) { continue; }
            return $v; // use as-is (keep case if you prefer)
        }
        return null; // default slot
    }

}
