<?php
namespace App\Controllers;

use App\Services\Database\DatabaseService;
use PDO as PDO;

class ContentAvailableController{
    protected $databaseService;
    private $dbs;
    private $bilingualGospel;
    private $gospel;
    private $video;
    public $languageCodeHL1;
    public $languageCodeHL2;
    private $query;
    private $params;

    public function __construct (DatabaseService $databaseService,  $languageCodeHL1, $languageCodeHL2 =  null){
        $this->databaseService = $databaseService;
        $this->languageCodeHL1 = $languageCodeHL1;
        if ($languageCodeHL1 !== null){
            $this->$languageCodeHL2 = $languageCodeHL2;
        }
        else{
            $this->languageCodeHL2 = $languageCodeHL1;
        } 
        $this->setContentAvailable();
    }
    public function setContentAvailable(){
        $this->dbs = $this->getDbsAvailable();
        $this->bilingualGospel = $this->getBilingualTractAvailable();
        $this->video = $this->getVideoAvailable();
    }

    public function getAllOptions(){
        $options = array(
            'dbs' => $this->dbs,
            'bilingualGospel' => $this->bilingualGospel,
            'video' => $this->video
        );
        return $options;
    }
    public function getDbsAvailable(){
        $this->query = "SELECT languageCodeHL
            FROM dbs_languages
            WHERE languageCodeHL = :languageCodeHL1
            OR languageCodeHL = :languageCodeHL2
            LIMIT 1";
        $this->params= array(':languageCodeHL1' => $this->languageCodeHL1 , ':languageCodeHL2' => $this->languageCodeHL2);
        return ($this-> getResponse());
    }
    public function getBilingualTractAvailable(){
        $this->query = "SELECT languageCodeHL1
            FROM hl_bilingual_tracts
            WHERE languageCodeHL1 = :languageCodeHL1
            OR languageCodeHL1 = :languageCodeHL2
            LIMIT 1";
        $this->params= array(':languageCodeHL1' => $this->languageCodeHL1 , ':languageCodeHL2' => $this->languageCodeHL2);
        return ($this-> getResponse());
        
    }
    public function getVideoAvailable(){
        $this->query = "SELECT languageCodeHL
            FROM jesus_video_languages
            WHERE languageCodeHL = :languageCodeHL1
            OR languageCodeHL = :languageCodeHL2
            LIMIT 1";
        $this->params= array(':languageCodeHL1' => $this->languageCodeHL1 , ':languageCodeHL2' => $this->languageCodeHL2);
        return ($this-> getResponse());
        
    }
    private function getResponse(){
        try {
            $results = $databaseService->executeQuery($this->query, $this->params);
            $data = $results->fetch(PDO::FETCH_ASSOC);
            if ($data){
                return true;
            }
            else{
                return false;
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
}