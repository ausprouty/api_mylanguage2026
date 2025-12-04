<?php

declare(strict_types=1);

/*
cd api2.mylanguage.net.au/Maintenance/GoogleLanguages/php/
php update-numerals-for-languages.php


*/

require_once dirname(__DIR__, 3) .'/vendor/autoload.php';
require_once __DIR__ . '/MapLanguageScripts.php';

use App\Configuration\Config;
use App\Services\Database\DatabaseService;

// Initialise configuration so Config::get() works.
Config::initialize();


// Use the standard DB connection profile.

$db = new DatabaseService('standard');

// Fetch rows that need filling
$sql = "SELECT id, languageCodeGoogle
        FROM hl_languages
        WHERE script IS NULL OR script = '' 
           OR numeralSet IS NULL OR numeralSet = ''";
$rows = $db->fetchAll($sql);

// Prepare the update once
$updateSql = 'UPDATE hl_languages
              SET script = :script,
                  numeralSet = :numeralSet
              WHERE id = :id';

$updateStmt = $db->prepare($updateSql);

foreach ($rows as $row) {
    $meta = getScriptAndNumeralsForGoogle($row['languageCodeGoogle'] ?? null);
    $updateStmt->execute([
        ':script'     => $meta['script'],
        ':numeralSet' => $meta['numeralSet'],
        ':id'         => $row['id'],
    ]);
}
