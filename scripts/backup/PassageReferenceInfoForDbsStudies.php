<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\Database\DatabaseService;
use App\Factories\BibleStudyReferenceFactory;

$databaseService = new DatabaseService();
$factory = new BibleStudyReferenceFactory($databaseService);

$query = 'SELECT * FROM dbs_references';
$results = $databaseService->executeQuery($query);
$rows = $results->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    if ($row['passage_reference_info']) {
        $info = json_decode($row['passage_reference_info']);
        if ($info->bookID) {
            $info->passageID = $info->bookID . '-' . $info->chapterStart .
                '-' . $info->verseStart . '-' . $info->verseEnd;
        }
        $info->uversionBookID = uversionBookID($info->bookID,$databaseService);
        $passage_reference_info = json_encode($info);
        $query = 'UPDATE dbs_references SET passage_reference_info = :info
                  WHERE lesson = :lesson';
        $params = array(
            ':info' => $passage_reference_info,
            ':lesson' => $row['lesson']
        );
        $databaseService->executeQuery($query, $params);
    }
}
exit;

function uversionBookID($bookID, $databaseService){
    
    $query = 'SELECT uversionBookID FROM bible_books WHERE bookID = :bookID';
    $params = array(':bookID'=> $bookID);
    $uversion = $databaseService->FetchSingleValue($query, $params);
    return $uversion;

}


