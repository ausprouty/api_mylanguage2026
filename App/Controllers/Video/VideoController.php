<?php
namespace App\Controllers\Video;

use App\Services\Database\DatabaseService;
use PDO as PDO;

class VideoController  {

    protected $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    // input videoCode is 6_529 -GOLUKE
    private function changeVideoLanguage($languageCodeJF){
        $this->videoCode = str_replace('529', $langugeCodeJF, $this->videoCode);
    }

    static function getVideoCodeFromTitle($title, $languageCodeHL){
        $title = str_ireplace('%20', ' ', $title);
        $query = "SELECT videoCode FROM jesus_video_languages
            WHERE title = :title AND languageCodeHL = :languageCodeHL
            ORDER BY weight DESC";
        $params = array(':title'=> $title, ':languageCodeHL'=> $languageCodeHL);
        try {
            $results = $databaseService->executeQuery($query, $params);
            $videoCode = $results->fetch(PDO::FETCH_COLUMN);
            return $videoCode;
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }

    }
}
