<?php
declare(strict_types=1);

// Adjust the path if google-languages.json is elsewhere
$jsonPath = __DIR__ . '/../google-languages.json';

if (!file_exists($jsonPath)) {
    fwrite(STDERR, "File not found: $jsonPath\n");
    exit(1);
}

$json = file_get_contents($jsonPath);
if ($json === false) {
    fwrite(STDERR, "Could not read $jsonPath\n");
    exit(1);
}

$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "JSON decode error: " . json_last_error_msg() . "\n");
    exit(1);
}

if (!isset($data['data']['languages'])) {
    fwrite(STDERR, "Unexpected JSON structure. Raw JSON:\n$json\n");
    exit(1);
}

$languages = $data['data']['languages'];

// Header row (goes to STDOUT)
echo "languageCodeGoogle,languageName\n";

foreach ($languages as $lang) {
    $code = $lang['language'] ?? '';
    $name = $lang['name'] ?? '';

    if ($code === '') {
        continue;
    }

    // Very simple CSV escaping
    $codeCsv = '"' . str_replace('"', '""', $code) . '"';
    $nameCsv = '"' . str_replace('"', '""', $name) . '"';

    echo $codeCsv . ',' . $nameCsv . "\n";
}
