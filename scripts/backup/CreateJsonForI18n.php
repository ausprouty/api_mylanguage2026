<?php
function csvToStructuredJson($filePath, $outputDir)
{
    $file = fopen($filePath, 'r');
    if (!$file) {
        die("Failed to open file");
    }

    stream_filter_append($file, 'convert.iconv.UTF-8/UTF-8');

    $maxLength = 262144; // Or even higher
    $keys = fgetcsv($file, $maxLength, "\t");

    while (($row = fgetcsv($file, 65536, "\t")) !== false) {
        $entry = [];
        $hlCode = '';
        $nonEmptyCount = 0;

        if (count($row) < count($keys)) {
            echo "Row too short: " . implode("\t", $row) . "\n";
        }

        if (count($row) > count($keys)) {
            echo "Row has more columns than keys: " . implode("\t", $row) . "\n";
        }

        foreach ($keys as $index => $key) {
            if (!empty($key)) {
                $value = $row[$index] ?? null;

                // Skip empty values for count check
                if (!in_array($key, ['language.HLcode', 'language.name']) && !empty($value)) {
                    $nonEmptyCount++;
                }

                $path = explode('.', $key);
                $current = &$entry;

                foreach ($path as $part) {
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }

                $current = $value;

                if ($key === 'language.HLcode') {
                    $hlCode = $value;
                }
            }
        }

        // Only write file if hlCode exists and at least 1 other field has data
        if (!empty($hlCode) && $nonEmptyCount > 3) {
            $outputFile = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hlCode . '.json';

            if (!is_dir(dirname($outputFile))) {
                mkdir(dirname($outputFile), 0755, true);
            }

            echo 'Writing file: ' . $outputFile . "\n";
            file_put_contents($outputFile, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            echo "Skipping file for hlCode: $hlCode\n";
        }
    }

    fclose($file);
}

$filePath = 'data/i18n.txt';
$outputDir = 'c:/ampp82/htdocs/api_mylanguage/resources/translations/i18n/';

csvToStructuredJson($filePath, $outputDir);
