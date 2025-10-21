<?php
namespace App\Repositories;

use App\Services\Database\DatabaseService;
use PDO;

class BibleGatewayRepository extends BaseRepository
{
   

    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    public function recordExists($externalId)
    {
        $query = "SELECT bid FROM bibles WHERE source = :source AND externalId = :externalId LIMIT 1";
        $params = [':source' => 'bible_gateway', ':externalId' => $externalId];
        return $this->databaseService->fetchSingleValue($query, $params);
    }

    public function insertBibleRecord($data)
    {
        $query = "INSERT INTO bibles (source, externalId, volumeName, languageName, languageCodeIso, 
                  collectionCode, format, text, weight, dateVerified) 
                  VALUES (:source, :externalId, :volumeName, :languageName, :languageCodeIso, 
                  :collectionCode, :format, :text, :weight, :dateVerified)";
        $this->databaseService->executeQuery($query, $data);
    }

    public function updateBibleWeight($bid, $weight)
    {
        $query = "UPDATE bibles SET weight = :weight WHERE bid = :bid LIMIT 1";
        $this->databaseService->executeQuery($query, [':weight' => $weight, ':bid' => $bid]);
    }
    
    // Other methods for updating, checking, and inserting into `hl_languages`...
}
