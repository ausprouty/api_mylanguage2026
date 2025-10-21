<?php

namespace App\Repositories;

use App\Services\Database\DatabaseService;

class BibleBookRepository extends BaseRepository
{
   

    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    public function getBookName($languageCodeHL, $bookID): ?string
    {
        $query = "SELECT name FROM bible_book_names WHERE languageCodeHL = :languageCodeHL AND bookID = :bookID LIMIT 1";
        $params = [':languageCodeHL' => $languageCodeHL, ':bookID' => $bookID];
        return $this->databaseService->fetchSingleValue($query, $params);
    }

    public function saveBookName($bookID, $languageCodeHL, $name): void
    {
        $query = "INSERT INTO bible_book_names (bookId, languageCodeHL, name) VALUES (:bookId, :languageCodeHL, :name)";
        $params = [':bookId' => $bookID, ':languageCodeHL' => $languageCodeHL, ':name' => $name];
        $this->databaseService->executeQuery($query, $params);
    }
}
