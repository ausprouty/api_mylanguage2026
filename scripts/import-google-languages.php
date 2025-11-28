<?php
declare(strict_types=1);

// Show all errors in CLI
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Starting import-google-languages...\n";

require_once __DIR__ . '/../vendor/autoload.php';

use App\Configuration\Config;
use App\Services\Database\DatabaseService;

Config::initialize();

// Create DatabaseService directly (it uses Config::get() internally)
$db = new DatabaseService('standard'); // or whatever profile you normally use
echo "Got DatabaseService of class: " . get_class($db) . "\n";

// CSV path
$csvPath = __DIR__ . '/../google-languages.csv';
echo "CSV path: $csvPath\n";

if (!file_exists($csvPath)) {
    echo "ERROR: File not found: $csvPath\n";
    exit(1);
}

$fh = fopen($csvPath, 'r');
if ($fh === false) {
    echo "ERROR: Could not open $csvPath\n";
    exit(1);
}

echo "File opened successfully. Reading header...\n";

// Skip header row
$header = fgetcsv($fh);
echo "Header row:\n";
var_dump($header);

// SQL using DatabaseService::executeQuery()
$sql = "
    INSERT INTO languages_google (languageCodeGoogle, languageName)
    VALUES (:code, :name)
    ON DUPLICATE KEY UPDATE languageName = VALUES(languageName)
";

$line  = 1;
$count = 0;

echo "Starting CSV loop...\n";

while (($row = fgetcsv($fh)) !== false) {
    $line++;

    // Expect [0] => languageCodeGoogle, [1] => languageName
    if (count($row) < 2) {
        echo "Skipping line $line (not enough columns)\n";
        continue;
    }

    $code = trim($row[0]);
    $name = trim($row[1]);

    if ($code === '' || $name === '') {
        echo "Skipping line $line (blank code or name)\n";
        continue;
    }

    // Debug first few lines
    if ($count < 5) {
        echo "Inserting line $line: code='$code', name='$name'\n";
    }

    // IMPORTANT: use executeQuery on DatabaseService, not on a statement
    $db->executeQuery($sql, [
        ':code' => $code,
        ':name' => $name,
    ]);

    $count++;
}

fclose($fh);

echo "Finished. Imported/updated {$count} rows into languages_google.\n";
