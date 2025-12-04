<?php
declare(strict_types=1);

/**
 * export-dbs-languages.php
 *
 * Usage (CLI):
 *   cd api2.mylanguage.net.au/Maintenance/Shared/php
 *   php get-language-index-for-dbsmaker.php > dbs-languages.json
 */

require_once dirname(__DIR__, 3) .'/vendor/autoload.php';

use App\Configuration\Config;
use App\Services\Database\DatabaseService;

Config::initialize();

// Use the standard DB connection profile.
// Adjust the configType if you have a special one for DBS.
$db = new DatabaseService('standard');

// Pull languages from dbs_languages and enrich from hl_languages
$sql = <<<SQL
SELECT
    dl.languageCodeHL,
    hl.name AS name,
    hl.ethnicName,
    hl.languageCodeGoogle,
    hl.languageCodeJF,
    hl.languageCodeIso,
    hl.direction,
    hl.numeralSet
FROM dbs_languages AS dl
JOIN hl_languages AS hl
  ON hl.languageCodeHL = dl.languageCodeHL
ORDER BY name
SQL;

$rows = $db->fetchAll($sql);

$languages = [];
$id = 5; // 5, 10, 15, ... for manual reordering

foreach ($rows as $row) {
    $hlCode  = $row['languageCodeHL'];
    $isoCode = $row['languageCodeIso'];

   // Normalise name and ethnicName
    $name    = trim((string) $row['name']);
    $ethnic  = trim((string) ($row['ethnicName'] ?? ''));

    // If ethnicName is blank or effectively the same as name,
    // hide it in the export (set to null).
    if ($ethnic === '') {
        $ethnic = null;
    } else {
        // Case-insensitive compare, Unicode-aware
        $nameLower   = mb_strtolower($name, 'UTF-8');
        $ethnicLower = mb_strtolower($ethnic, 'UTF-8');
        if ($nameLower === $ethnicLower) {
            $ethnic = null;
        }
    }
    // Normalize direction
    $rawDirection = isset($row['direction']) ? (string) $row['direction'] : '';

    $dir = strtolower(trim($rawDirection));

    if ($dir === 'rtl') {
        $textDirection = 'rtl';
    } else {
        $textDirection = 'ltr'; // default
    }


    $languages[] = [
        'id'                 => $id,
        'name'               => $name,
        'ethnicName'         => $ethnic,
        'languageCodeHL'     => $hlCode,
        'languageCodeGoogle' => $row['languageCodeGoogle'],
        'languageCodeJF'     => $row['languageCodeJF'],
        'textDirection'      => $textDirection ,
        'numeralSet'         => $row['numeralSet']
    ];

    $id += 5;
}

// Pretty JSON, preserving Unicode (for ethnicName)
echo json_encode(
    $languages,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
) . PHP_EOL;
