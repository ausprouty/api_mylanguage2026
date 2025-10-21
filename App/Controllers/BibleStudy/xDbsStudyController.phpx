<?php

namespace App\Controllers\BibleStudy;

use App\Models\Bible\BibleModel as BibleModel;
use App\Services\Database\DatabaseService;
use App\Services\Language\TranslationService as TranslationService;
use PDO as PDO;
use StdClass as StdClass;

class DbsStudyController
{
    private $data;
    protected $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
        $query = "SELECT * FROM dbs_references
        ORDER BY lesson";
        try {
            $results = $databaseService->executeQuery($query);
            $this->data = $results->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
    public function formatWithEnglishTitle()
    {
        $formated = [];
        foreach ($this->data as $lesson) {
            $title = $lesson['lesson'] . '. ' . $lesson['description']  . ' (' . $lesson['reference'] . ')';
            $obj =  new stdClass();
            $obj->title = $title;
            $obj->lesson = $lesson['lesson'];
            $obj->testament = $lesson['testament'];
            $formatted[] = $obj;
        }
        return $formatted;
    }
    public function formatWithEthnicTitle($languageCodeHL)
    {
        $formated = [];
        $otAvailable = BibleModel::oldTestamentAvailable($languageCodeHL);
        $translation = new TranslationService($languageCodeHL, 'dbs');
        foreach ($this->data as $lesson) {
            if ($lesson['testament'] == 'NT' || ($lesson['testament'] == 'OT' && $otAvailable)) {
                $translated = $translation->translateText($lesson['description']);
                $title = $lesson['lesson'] . '. ' . $translated;
                $obj =  new stdClass();
                $obj->title = $title;
                $obj->lesson = $lesson['lesson'];
                $obj->testament = $lesson['testament'];
                $formatted[] = $obj;
            }
        }
        return $formatted;
    }
    static function getTitle($lesson, $languageCodeHL)
    {
        $databaseService = new DatabaseService();
        if ($languageCodeHL != 'eng00') {
            $translation = new TranslationService($languageCodeHL, 'dbs');
        }
        $query = "SELECT description FROM dbs_references
        WHERE lesson = :lesson";
        $params = array(':lesson' => $lesson);
        try {
            $results = $databaseService->executeQuery($query, $params);
            $title = $results->fetch(PDO::FETCH_COLUMN);
            if ($languageCodeHL != 'eng00') {
                $title = $translation->translateText($title);
            }
            return $lesson . '. ' . $title;
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
}
