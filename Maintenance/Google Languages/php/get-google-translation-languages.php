<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
use App\Configuration\Config;
Config::initialize();

$apiKey = Config::get('api.google_translate_apiKey');

if ($apiKey === '') {
    fwrite(STDERR, "Missing API key.\n");
    exit(1);
}

$url = 'https://translation.googleapis.com/language/translate/v2/languages'
     . '?target=en&key=' . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FAILONERROR    => false,
    CURLOPT_TIMEOUT        => 20,
]);

$json = curl_exec($ch);

if ($json === false) {
    fwrite(STDERR, "cURL error: " . curl_error($ch) . "\n");
    curl_close($ch);
    exit(1);
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    fwrite(STDERR, "HTTP $httpCode from Google:\n$json\n");
    exit(1);
}

$data = json_decode($json, true);

if (isset($data['error'])) {
    $message = $data['error']['message'] ?? 'Unknown Google API error';
    $code    = $data['error']['code'] ?? 'n/a';
    fwrite(STDERR, "Google API error ($code): $message\n");
    exit(1);
}

if (!isset($data['data']['languages'])) {
    fwrite(STDERR, "Unexpected response from Google:\n$json\n");
    exit(1);
}

$languages = $data['data']['languages'];

echo "languageCodeGoogle,languageName\n";
foreach ($languages as $lang) {
    $code = $lang['language'] ?? '';
    $name = $lang['name'] ?? '';

    if ($code === '') {
        continue;
    }

    $codeCsv = '"' . str_replace('"', '""', $code) . '"';
    $nameCsv = '"' . str_replace('"', '""', $name) . '"';

    echo $codeCsv . ',' . $nameCsv . "\n";
}
