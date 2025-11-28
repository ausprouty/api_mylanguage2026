<?php
declare(strict_types=1);

/**
 * get-google-translation-languages.php
 *
 * Purpose:
 *   Query the Google Translate API for the full list of languages it supports
 *   and print them to STDOUT as CSV:
 *
 *     languageCodeGoogle,languageName
 *
 *   This CSV can then be redirected to a file and imported into the
 *   `languages_google` table for further processing.
 *
 * Usage (from project root):
 *   php Maintenance/GoogleLanguage/php/get-google-translation-languages.php \
 *       > Maintenance/GoogleLanguage/data/languages_google_raw.csv
 *
 * Requirements:
 *   - Composer autoload file at ../vendor/autoload.php
 *   - Config::get('api.google_translate_apiKey') must return a valid
 *     Google Translate API key.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Configuration\Config;

// Initialise configuration so Config::get() works.
Config::initialize();

/**
 * Fetch the Google Translate API key from central config.
 * We fail fast if it's missing to avoid a mysterious HTTP 403.
 */
$apiKey = Config::get('api.google_translate_apiKey');

if ($apiKey === '') {
    fwrite(STDERR, "ERROR: Missing Google Translate API key.\n");
    exit(1);
}

/**
 * Build the URL for the "list languages" endpoint.
 *
 * Docs:
 *   https://cloud.google.com/translate/docs/basic/languages
 *
 * We ask for English display names so that languageName is readable.
 */
$url = 'https://translation.googleapis.com/language/translate/v2/languages'
     . '?target=en&key=' . urlencode($apiKey);

/**
 * Prepare cURL request.
 * We:
 *   - Return the response as a string
 *   - Set a sensible timeout
 *   - Verify SSL by default
 */
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

// Perform the HTTP request.
$json = curl_exec($ch);

if ($json === false) {
    // Low-level cURL/network error.
    $err = curl_error($ch);
    fwrite(STDERR, "ERROR: cURL error: {$err}\n");
    curl_close($ch);
    exit(1);
}

// Capture HTTP status for clearer diagnostics.
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    // Non-OK response (e.g. 401, 403, 500, etc.).
    fwrite(
        STDERR,
        "ERROR: HTTP status {$httpCode} from Google:\n{$json}\n"
    );
    exit(1);
}

// Decode JSON response as associative array.
$data = json_decode($json, true);

if ($data === null) {
    fwrite(
        STDERR,
        "ERROR: Failed to decode JSON from Google:\n{$json}\n"
    );
    exit(1);
}

// Google may return an "error" object in a 200 OK body.
if (isset($data['error'])) {
    $message = $data['error']['message'] ?? 'Unknown Google API error';
    $code    = $data['error']['code'] ?? 'n/a';
    fwrite(
        STDERR,
        "ERROR: Google API error ({$code}): {$message}\n"
    );
    exit(1);
}

// Normal happy path: expect data.data.languages.
if (!isset($data['data']['languages'])
    || !is_array($data['data']['languages'])
) {
    fwrite(
        STDERR,
        "ERROR: Unexpected response structure from Google:\n{$json}\n"
    );
    exit(1);
}

$languages = $data['data']['languages'];

/**
 * Output CSV header.
 * We keep it simple on purpose so it is easy to import into MySQL:
 *
 *   languageCodeGoogle,languageName
 */
echo "languageCodeGoogle,languageName\n";

/**
 * Each entry from Google has:
 *   - language : e.g. "en", "fr", "zh-TW"
 *   - name     : e.g. "English", "French", "Chinese (Traditional)"
 *
 * We:
 *   - skip any entry missing a language code
 *   - escape double quotes in both fields
 */
foreach ($languages as $lang) {
    $code = $lang['language'] ?? '';
    $name = $lang['name'] ?? '';

    if ($code === '') {
        // No language code – nothing useful to store.
        continue;
    }

    // Minimal CSV escaping: double internal quotes.
    $codeCsv = '"' . str_replace('"', '""', $code) . '"';
    $nameCsv = '"' . str_replace('"', '""', $name) . '"';

    echo $codeCsv . ',' . $nameCsv . "\n";
}
