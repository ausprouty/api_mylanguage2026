<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\Database\DatabaseService;
use App\Factories\BibleStudyReferenceFactory;
use App\Factories\BibleReferenceModelFactory;
use App\Repositories\BibleReferenceRepository;

$databaseService = new DatabaseService();
$factory = new BibleStudyReferenceFactory($databaseService);
$repository = new BibleReferenceRepository($databaseService);

$query = 'SELECT * FROM life_principle_references';
$results = $databaseService->executeQuery($query);
$rows = $results->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    if ($row['passage_reference_info']) {
        $info = json_decode($row['passage_reference_info']);
        if ($info->bookID) {
            $info->passageID = $info->bookID . '-' . $info->chapterStart .
                '-' . $info->verseStart . '-' . $info->verseEnd;
        }
    }
    else {
        $factory = new BibleReferenceModelFactory($repository);
        $model = $factory->createFromEntry($row['reference']);
        $info = new stdClass();
        $info->entry = $model->getEntry();
        $info->bookName = $model->getBookName();
        $info->bookID = $model->getBookID();
        $info->testament = $model->getTestament();
        $info->chapterStart = $model->getChapterStart() ;
        $info->chapterEnd = $model->getChapterStart();
        $info->verseStart = $model->getVerseStart();
        $info->verseEnd = $model->getVerseEnd();
    }
    $info->uversionBookID = uversionBookID($info->bookID,$databaseService);
   
    $info->passageID = $info->bookID . '-' . $info->chapterStart .
                '-' . $info->verseStart . '-' . $info->verseEnd;
    $passage_reference_info = json_encode($info);
    $query = 'UPDATE life_principle_references SET passage_reference_info = :info
                WHERE lesson = :lesson';
    $params = array(
        ':info' => $passage_reference_info,
        ':lesson' => $row['lesson']
    );
    $databaseService->executeQuery($query, $params);
}
echo 'finished';

function uversionBookID($bookID, $databaseService){
    
    $query = 'SELECT uversionBookID FROM bible_books WHERE bookID = :bookID';
    $params = array(':bookID'=> $bookID);
    $uversion = $databaseService->FetchSingleValue($query, $params);
    return $uversion;

}
