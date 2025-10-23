<?php
declare(strict_types=1);

/*
  CamelCase-only translation coverage probe (no DI).

  Example:
    http://localhost:9333/dev/translation_probe.php
      ?kind=interface&subject=app&lang=eng00&variant=wsu

  Extras:
    &masterForEnglish=0   → disable master fallback
*/

header('Content-Type: application/json; charset=utf-8');

function out(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$kind    = trim($_GET['kind']    ?? 'interface');
$subject = trim($_GET['subject'] ?? 'app');
$variant = trim($_GET['variant'] ?? '');
$lang    = trim($_GET['lang']    ?? 'eng00');

if ($kind === '' || $subject === '' || $lang === '') {
    out(400, ['status'=>'error','error'=>'Missing kind/subject/lang']);
}

/* --- DB CONFIG: adjust to your local values --- */
$dsn  = 'mysql:host=127.0.0.1;dbname=api_mylanguage;charset=utf8mb4';
$user = 'root';
$pass = '';
/* --------LIMIT AND SOURCE COUNTS------------------ */
$sampleLimit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;

$statusCounts = [
  'approved' => 0, 'review' => 0, 'machine' => 0, 'draft' => 0, 'none' => 0
];
$sourceCounts = ['translation' => 0, 'master_fallback' => 0];
/* ---------------------------------------------- */
try {
    $pdo = new PDO(
        $dsn, $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    out(500, ['status'=>'error','error'=>$e->getMessage()]);
}

/** Helpers **/
function colExists(PDO $pdo, string $table, string $col): bool {
    $sql = "SELECT 1
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :t
               AND COLUMN_NAME  = :c";
    $s = $pdo->prepare($sql);
    $s->execute([':t'=>$table, ':c'=>$col]);
    return (bool)$s->fetchColumn();
}

function pickCamel(PDO $pdo, string $table, array $candidates, string $label): string {
    foreach ($candidates as $c) {
        if ($c !== null && colExists($pdo, $table, $c)) return $c;
    }
    $msg = "Couldn't find $label in $table (expected camelCase; tried: "
         . implode(', ', array_filter($candidates)) . ")";
    out(500, ['status'=>'error','error'=>$msg]);
}

/** Table names **/
$T_RES = 'i18n_resources';
$T_STR = 'i18n_strings';
$T_TRN = 'i18n_translations';

/** Detect key columns (camelCase only) **/

// i18n_resources
$RES_ID   = pickCamel($pdo, $T_RES, ['resourceId'], 'resource id');
$RES_TYPE = pickCamel($pdo, $T_RES, ['type'], 'resources.type');
$RES_SUBJ = pickCamel($pdo, $T_RES, ['subject'], 'resources.subject');
$RES_VAR  = pickCamel($pdo, $T_RES, ['variant'], 'resources.variant');

// i18n_strings
$STR_ID       = pickCamel($pdo, $T_STR, ['stringId'], 'string id');
$STR_RES_ID   = pickCamel($pdo, $T_STR, ['resourceId'], 'strings.resourceId');
$STR_KEY      = pickCamel($pdo, $T_STR, ['keyHash','stringKey','keyName','name','devKey'], 'string key');
$STR_EN       = pickCamel($pdo, $T_STR, ['englishText','textEn','masterText','sourceEn'], 'master English text');
$STR_ISACTIVE = colExists($pdo, $T_STR, 'isActive') ? 'isActive' : null;

// i18n_translations
$TRN_ID     = pickCamel($pdo, $T_TRN, ['translationId','id'], 'translation id');
$TRN_STR_ID = pickCamel($pdo, $T_TRN, ['stringId'], 'translations.stringId');
$TRN_LANG   = pickCamel($pdo, $T_TRN, ['languageCodeIso'], 'target language');
$TRN_TEXT   = pickCamel($pdo, $T_TRN, ['translatedText','text'], 'translation text');
$TRN_FUZZY  = colExists($pdo, $T_TRN, 'isFuzzy') ? 'isFuzzy' : null;

/** Find the resource row **/
$sqlRes =
    "SELECT `$RES_ID` AS rid
       FROM $T_RES
      WHERE `$RES_TYPE` = :kind
        AND `$RES_SUBJ` = :subject
        AND (`$RES_VAR` <=> :variant)";

$stmt = $pdo->prepare($sqlRes);
$v = ($variant === '') ? null : $variant;
$stmt->execute([':kind'=>$kind, ':subject'=>$subject, ':variant'=>$v]);
$res = $stmt->fetch();

if (!$res) {
    out(404, [
        'status' => 'error',
        'error'  => 'Resource not found',
        'input'  => compact('kind','subject','variant')
    ]);
}

$resourceId = (int)$res['rid'];

/** HL ↔ ISO mapper **/
function mapHlToIso(PDO $pdo, string $hl): ?string {
    $sql = "SELECT languageCodeIso FROM hl_languages WHERE languageCodeHL = :hl LIMIT 1";
    $s = $pdo->prepare($sql);
    $s->execute([':hl' => $hl]);
    $iso = $s->fetchColumn();
    return $iso ? (string)$iso : null;
}

$langHl  = $lang;
$langIso = mapHlToIso($pdo, $langHl) ?? $langHl;

// optional toggle
$useMasterForEnglish = isset($_GET['masterForEnglish'])
    ? ($_GET['masterForEnglish'] === '1')
    : true;

/** Pull strings + joined translations (HL or ISO match) **/
$sql =
  "SELECT
      s.`$STR_ID`    AS string_id,
      s.`$STR_KEY`   AS `key`,
      s.`$STR_EN`    AS master_en,
      t.`$TRN_TEXT`  AS translated_text,
      t.`$TRN_ID`    AS tr_id,
      t.`status`    AS tr_status,
      CASE
        WHEN t.`languageCodeIso` = :langIso THEN 'iso'
        WHEN t.`languageCodeHL`  = :langHl  THEN 'hl'
        ELSE NULL
      END AS matched_on
   FROM $T_STR s
   LEFT JOIN $T_TRN t
     ON t.`$TRN_STR_ID` = s.`$STR_ID`
    AND (t.`languageCodeIso` = :langIso OR t.`languageCodeHL` = :langHl)
   WHERE s.`$STR_RES_ID` = :rid"
  . ($STR_ISACTIVE ? " AND s.`$STR_ISACTIVE` = 1" : "") .
  " ORDER BY s.`$STR_ID`";

$stmt = $pdo->prepare($sql);
$stmt->execute([':langIso'=>$langIso, ':langHl'=>$langHl, ':rid'=>$resourceId]);

$present = 0;
$missing = [];
$fuzzy   = [];
$presentSamples = [];
$allCount = 0;

while ($row = $stmt->fetch()) {
    $allCount++;

    // Effective text with optional fallback
    $effective = $row['translated_text'];
    if (($effective === null || $effective === '') && $useMasterForEnglish) {
        if ($langIso === 'en' || $langHl === 'eng00') {
            $effective = $row['master_en'];
        }
    }

    if ($effective !== null && $effective !== '') {
        $present++;
        $src = ($row['matched_on'] ? 'translation' : 'master_fallback');
        $sourceCounts[$src]++;

        $st = $row['tr_status'] ?: 'none';
        if (!isset($statusCounts[$st])) { $st = 'none'; }
        $statusCounts[$st]++;

        if (count($presentSamples) < $sampleLimit) {
            $presentSamples[] = [
            'id'         => (int)$row['string_id'],
            'key'        => $row['key'],
            'en'         => $row['master_en'],
            'matched_on' => $row['matched_on'],
            'status'     => $row['tr_status'],
            'source'     => $src,
            ];
        }
    } else {
        $reason = $row['tr_id'] ? 'blank_text' : 'no_row';
        $statusCounts[$row['tr_status'] ?: 'none']++;
        if (count($missing) < $sampleLimit) {
            $missing[] = [
            'id'         => (int)$row['string_id'],
            'key'        => $row['key'],
            'en'         => $row['master_en'],
            'why_missing'=> $reason,
            'status'     => $row['tr_status'],
            ];
        }
    }
}


out(200, [
    'status' => 'ok',
    'meta' => [
        'kind'         => $kind,
        'subject'      => $subject,
        'variant'      => ($variant === '') ? null : $variant,
        'lang'         => $lang,
        'lang_iso'     => $langIso,
        'lang_hl'      => $langHl,
        'masterFallbackForEnglish' => $useMasterForEnglish,
        'resource_id'  => $resourceId,
        'tables'       => [
            'resources'    => $T_RES,
            'strings'      => $T_STR,
            'translations' => $T_TRN,
        ],
        'columns' => [
            'RES_ID'   => $RES_ID,
            'RES_TYPE' => $RES_TYPE,
            'RES_SUBJ' => $RES_SUBJ,
            'RES_VAR'  => $RES_VAR,
            'STR_ID'   => $STR_ID,
            'STR_RES'  => $STR_RES_ID,
            'STR_KEY'  => $STR_KEY,
            'STR_EN'   => $STR_EN,
            'TRN_STR'  => $TRN_STR_ID,
            'TRN_LANG' => 'languageCodeIso|languageCodeHL',
            'TRN_TEXT' => $TRN_TEXT,
            'TRN_FUZZY'=> $TRN_FUZZY,
        ],
        'total'        => $allCount,
        'present'      => $present,
        'missing'      => max(0, $allCount - $present),
        'fuzzy'        => 0,
        'coverage_pct' => ($allCount > 0) ? round(($present / $allCount) * 100, 2) : 0.0,
        'status_counts' => $statusCounts,
        'source_counts' => $sourceCounts,
        'sample_limit'  => $sampleLimit,
    ],
    'samples' => [
        'present' => $presentSamples,
        'missing' => $missing,
    ],
]);
