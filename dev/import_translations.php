<?php
// dev/import_translations.php (POST a CSV file `file`)
declare(strict_types=1);
if (!isset($_FILES['file']) || $_FILES['file']['error']) {
  http_response_code(400); echo "No file"; exit;
}
$pdo = new PDO('mysql:host=127.0.0.1;dbname=api_mylanguage;charset=utf8mb4',
               'root','', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

$f = fopen($_FILES['file']['tmp_name'], 'r');
$hdr = fgetcsv($f);
$idx = array_flip($hdr);

$ins = $pdo->prepare(
 "INSERT INTO i18n_translations
    (stringId, languageCodeHL, languageCodeIso,
     translatedText, status, source, posted)
  VALUES
    (:sid, :hl, :iso, :txt, :st, 'import', CURDATE())
  ON DUPLICATE KEY UPDATE
    translatedText = VALUES(translatedText),
    status         = VALUES(status),
    source         = VALUES(source),
    posted         = VALUES(posted)");

$pdo->beginTransaction();
while (($r = fgetcsv($f)) !== false) {
  $ins->execute([
    ':sid' => (int)$r[$idx['stringId']],
    ':hl'  => $r[$idx['languageCodeHL']],
    ':iso' => $r[$idx['languageCodeIso']],
    ':txt' => $r[$idx['translatedText']],
    ':st'  => $r[$idx['status']] ?: 'draft',
  ]);
}
$pdo->commit();
echo "OK";
