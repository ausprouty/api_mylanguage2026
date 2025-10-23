<?php
// dev/export_translations.php?resourceId=1&langHL=gjr00
declare(strict_types=1);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="export.csv"');

$rid   = (int)($_GET['resourceId'] ?? 0);
$lang  = trim($_GET['langHL'] ?? '');
if ($rid <= 0 || $lang === '') { http_response_code(400); exit; }

$pdo = new PDO('mysql:host=127.0.0.1;dbname=api_mylanguage;charset=utf8mb4',
               'root','', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

$sql = "SELECT s.stringId, s.keyHash, s.englishText,
               t.translatedText, t.status
        FROM i18n_strings s
        LEFT JOIN i18n_translations t
          ON t.stringId = s.stringId AND t.languageCodeHL = :hl
        WHERE s.resourceId = :rid
        ORDER BY s.stringId";
$st = $pdo->prepare($sql);
$st->execute([':hl'=>$lang, ':rid'=>$rid]);

$out = fopen('php://output', 'w');
fputcsv($out, ['stringId','keyHash','englishText','translatedText','status']);
while ($row = $st->fetch(PDO::FETCH_NUM)) { fputcsv($out, $row); }
fclose($out);
