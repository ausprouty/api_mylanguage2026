<?php

namespace App\Controllers\Video;

use App\Services\Database\DatabaseService;
use App\Services\Language\TranslationService as TranslationService;
use PDO as PDO;
use stdClass as stdClass;
use Exception as Exception;
use App\Configuration\Config;




class JesusVideoSegmentController
{
    protected $databaseService;
    private $data;
    private $formatted;
    private $languageCodeJF;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
        $this->data = null;
        $this->formatted = null;
    }

    public function selectAllSegments()
    {

        $query = "SELECT * FROM jesus_video_segments
        ORDER BY id";
        try {
            $results = $this->databaseService->executeQuery($query);
            $this->data = $results->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }

    public function selectOneSegmentById($id)
    {

        $query = "SELECT * FROM jesus_video_segments
        WHERE id= :id";
        $params = array(':id' => $id);
        try {
            $results = $this->databaseService->executeQuery($query, $params);
            $this->data = $results->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }


    public function formatWithEnglishTitle()
    {
        $formated = [];
        foreach ($this->data as $segment) {
            $title = $segment['id'] . '. ' . $segment['title']  . ' (' . $segment['verses'] . ')';
            $formatted[] = $this->formatVideoSegment($title, $segment);
        }
        return $formatted;
    }

    public function formatWithEthnicTitle($languageCodeHL)
    {
        $formated = [];
        $translation = new TranslationService($languageCodeHL, 'video');
        foreach ($this->data as $segment) {
            $translated = $translation->translateText($segment['title']);
            $title = $segment['id'] . '. ' . $translated;
            $formatted[] = $this->formatVideoSegment($title, $segment);
        }
        return $formatted;
    }
    private function formatVideoSegment($title, $segment)
    {
        $obj =  new stdClass();
        $obj->id = $segment['id'];
        $obj->title = $title;
        $obj->src = $this->formatVideoSource($segment);
        $obj->videoSegment = $segment['videoSegment'];
        $obj->endTime = $segment['stopTime'];
        return $obj;
    }

    // src="https://api.arclight.org/videoPlayerUrl?refId=1_529-jf6101-0-0&amp;playerStyle=default"
    //returns src="https://api.arclight.org/videoPlayerUrl?refId=6_529-GOJohn2211&amp;start=170&amp;end=229
    protected function formatVideoSource($segment)
    {
        $url = Config::get('JVIDEO_SOURCE') . '1_' . $this->languageCodeJF . '-jf' . $segment['videoSegment'];
        $url .= '&start=0&end=' .  $segment['stopTime'];
        $url .= '&playerStyle=default"';
        return $url;
    }
}
