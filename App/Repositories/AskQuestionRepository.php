<?php
namespace App\Repositories;

use App\Services\Database\DatabaseService;
use App\Models\AskQuestionModel;
use PDO;

class AskQuestionRepository extends BaseRepository
{

    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    public function getBestSiteByLanguageCodeHL($code)
    {
        $query = "SELECT * FROM ask_questions WHERE languageCodeHL = :code ORDER BY weight DESC LIMIT 1";
        $params = [':code' => $code];

        // Fetch a single row using fetchRow, which returns [] if no result is found
        $data = $this->databaseService->fetchRow($query, $params);

        if (!empty($data)) {
            $askQuestion = new AskQuestionModel();
            $askQuestion->setValues($data);
            return $askQuestion;
        }

        return null;
    }
}
