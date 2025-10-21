<?php

namespace App\Repositories;

use App\Services\Database\DatabaseService;

class VideoRepository extends BaseRepository
{
   

    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    public function getLanguageCodeJF($languageCodeHL)
    {
        $query = "SELECT languageCodeJF FROM jesus_video_languages 
                  WHERE languageCodeHL = :languageCodeHL 
                  ORDER BY weight DESC LIMIT 1";
        $params = [':languageCodeHL' => $languageCodeHL];
        
        return $this->databaseService->fetchSingleValue($query, $params);
    }

    public function getLanguageCodeJFFollowingJesus($languageCodeHL)
    {
        $query = "SELECT languageCodeJF FROM jesus_video_languages 
                  WHERE languageCodeHL = :languageCodeHL 
                  AND title LIKE :following 
                  ORDER BY weight DESC LIMIT 1";
        $params = [
            ':languageCodeHL' => $languageCodeHL,
            ':following' => '%Following Jesus%'
        ];
        
        return $this->databaseService->fetchSingleValue($query, $params);
    }

    public function videoExists($videoCode): bool
    {
        $query = "SELECT videoCode FROM jesus_video_languages 
            WHERE videoCode = :videoCode LIMIT 1";
        $params = [':videoCode' => $videoCode];
        
        return (bool) $this->databaseService->fetchSingleValue($query, $params);
    }
}
