<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Configuration\Config;
use App\Services\Database\DatabaseService;

// -----------------------------------------------------------------------------
// CONFIGURATION
// -----------------------------------------------------------------------------

// Set to TRUE for testing (no database writes)
// Set to FALSE to perform actual updates
const DRY_RUN = false;

// Limit preview output (how many sample changed rows to display)
const PREVIEW_LIMIT = 50;

// -----------------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------------

Config::initialize();
$db = new DatabaseService('standard');

// -----------------------------------------------------------------------------
// 1. Load numeral systems into memory
// -----------------------------------------------------------------------------

$numeralRows = $db->fetchAll(
    'SELECT code, description,
            digit0, digit1, digit2, digit3, digit4,
            digit5, digit6, digit7, digit8, digit9
     FROM numeral_systems'
);

$numeralSystems = [];
$digitMaps      = [];

function buildDigitMap(array $row): array
{
    return [
        '0' => $row['digit0'],
        '1' => $row['digit1'],
        '2' => $row['digit2'],
        '3' => $row['digit3'],
        '4' => $row['digit4'],
        '5' => $row['digit5'],
        '6' => $row['digit6'],
        '7' => $row['digit7'],
        '8' => $row['digit8'],
        '9' => $row['digit9'],
    ];
}

foreach ($numeralRows as $row) {
    $code = $row['code'];
    $digitMaps[$code] = buildDigitMap($row);
}

$defaultDigitMap = $digitMaps['latn'] ?? [
    '0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4',
    '5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9'
];

// -----------------------------------------------------------------------------
// 2. Fetch passages + numeralSet via joins
// -----------------------------------------------------------------------------

$sql = 'SELECT bp.bpid,
               bp.referenceLocalLanguage,
               hl.numeralSet
        FROM bible_passages AS bp
        JOIN bibles AS b
          ON b.bid = SUBSTRING_INDEX(bp.bpid, "-", 1)
        JOIN hl_languages AS hl
          ON hl.languageCodeHL = b.languageCodeHL
        WHERE bp.referenceLocalLanguage IS NOT NULL
          AND bp.referenceLocalLanguage <> ""';

$passages = $db->fetchAll($sql);

// -----------------------------------------------------------------------------
// 3. Prepare UPDATE if not dry-run
// -----------------------------------------------------------------------------

if (!DRY_RUN) {
    $updateSql = 'UPDATE bible_passages
                  SET referenceLocalLanguage = :ref
                  WHERE bpid = :bpid';
    $updateStmt = $db->prepare($updateSql);
}

// -----------------------------------------------------------------------------
// 4. Helper
// -----------------------------------------------------------------------------

function convertDigitsForNumeralSet(
    string $text,
    ?string $numeralSet,
    array $digitMaps,
    array $defaultDigitMap
): string {
    $code = $numeralSet ?: 'latn';
    $digitMap = $digitMaps[$code] ?? $defaultDigitMap;
    return strtr($text, $digitMap);
}

// -----------------------------------------------------------------------------
// 5. Process
// -----------------------------------------------------------------------------

$changed = 0;
$previewShown = 0;
$preview = [];

foreach ($passages as $row) {
    $bpid   = $row['bpid'];
    $oldRef = $row['referenceLocalLanguage'];
    $set    = $row['numeralSet'] ?? 'latn';

    $newRef = convertDigitsForNumeralSet(
        $oldRef, $set, $digitMaps, $defaultDigitMap
    );

    if ($newRef === $oldRef) {
        continue;
    }

    $changed++;

    // Collect preview rows
    if ($previewShown < PREVIEW_LIMIT) {
        $preview[] = [
            'bpid' => $bpid,
            'old'  => $oldRef,
            'new'  => $newRef,
            'set'  => $set
        ];
        $previewShown++;
    }

    // Perform update only if DRY_RUN is false
    if (!DRY_RUN) {
        $updateStmt->execute([
            ':ref'  => $newRef,
            ':bpid' => $bpid,
        ]);
    }
}

// -----------------------------------------------------------------------------
// OUTPUT SUMMARY
// -----------------------------------------------------------------------------

echo "-------------------------------------------------\n";
echo DRY_RUN
    ? "DRY RUN MODE — NO DATABASE UPDATES PERFORMED\n"
    : "LIVE MODE — DATABASE UPDATED\n";
echo "-------------------------------------------------\n\n";

echo "Passages requiring digit conversion: $changed\n\n";

// Show preview
if (!empty($preview)) {
    echo "Sample changes (first " . PREVIEW_LIMIT . "):\n";
    echo "-------------------------------------------------\n";
    foreach ($preview as $p) {
        echo "BPID: {$p['bpid']}\n";
        echo "Numeral Set: {$p['set']}\n";
        echo "OLD: {$p['old']}\n";
        echo "NEW: {$p['new']}\n";
        echo "-------------------------------------------------\n";
    }
}
