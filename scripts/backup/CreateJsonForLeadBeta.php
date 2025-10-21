<?php
// Load the CSV file and parse its contents
function csvToStructuredJson($filePath, $outputDir)
{
    $file = fopen($filePath, 'r');

    if (!$file) {
        die("Failed to open file");
    }

    // Ensure proper encoding is set for reading the file
    stream_filter_append($file, 'convert.iconv.UTF-8/UTF-8');

    // Get the first row as keys
    $keys = fgetcsv($file, 0, '|');

    // Process each row of data
    while (($row = fgetcsv($file, 0, '|')) !== false) {
        $entry = [];
        $hlCode = '';

        foreach ($keys as $index => $key) {
            if (!empty($key)) {
                // Transform keys with periods into nested arrays
                $path = explode('.', $key);
                $current = &$entry;

                foreach ($path as $part) {
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }

                // Set the value for the last key in the path
                $current = $row[$index] ?? null;

                // Capture the code for the file name
                if ($key === 'language.hlCode') {
                    $hlCode = $row[$index];
                }
            }
        }

        if (!empty($hlCode)) {
            $outputFile = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hlCode . DIRECTORY_SEPARATOR . 'lead-beta.json';

            // Ensure the directory exists
            if (!is_dir(dirname($outputFile))) {
                mkdir(dirname($outputFile), 0755, true);
            }

            // Write the JSON file with proper encoding
            file_put_contents($outputFile, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    fclose($file);
}

// Replace this path with your uploaded file path
$filePath = 'c:/ampp82/htdocs/api_mylanguage/Resources/translations/LeaderBeta.txt';

// Replace this with your desired output directory
$outputDir = 'c:/ampp82/htdocs/api_mylanguage/Resources/translations/new';

// Call the function
csvToStructuredJson($filePath, $outputDir);
