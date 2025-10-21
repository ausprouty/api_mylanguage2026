<?php

namespace App\Repositories;

use App\Services\Database\DatabaseService;
use PDO;

/**
 * Repository for Bible references and related data retrieval.
 */
class PassageReferenceRepository extends BaseRepository
{
    /**
     * @var DatabaseService Database service instance for query execution.
     */
 

    /**
     * Constructor.
     *
     * @param DatabaseService $databaseService Service for database interactions.
     */
    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct($databaseService);
    }

    /**
     * Finds the book ID by language code and book name.
     *
     * @param string $languageCodeHL The language code.
     * @param string $bookName The name of the book.
     * @return mixed|null The book ID if found, otherwise null.
     */
    public function findBookID($bookName, $languageCodeHL= 'eng00')
    {
        $query = 'SELECT bookId FROM bible_book_names 
                  WHERE (languageCodeHL = :languageCodeHL OR languageCodeHL = :english) 
                  AND name = :book_lookup LIMIT 1';
        $params = [
            ':languageCodeHL' => $languageCodeHL,
            ':english' => 'eng00',
            ':book_lookup' => $bookName
        ];
        return $this->databaseService->fetchSingleValue($query, $params);
    }

    /**
     * Finds the book number by book ID.
     *
     * @param string $bookID The book ID.
     * @return mixed|null The book number if found, otherwise null.
     */
    public function findBookNumber($bookID)
    {
        $query = 'SELECT bookNumber FROM bible_books WHERE bookId = :bookId LIMIT 1';
        return $this->databaseService->fetchSingleValue($query, [':bookId' => $bookID]);
    }

    /**
     * Finds the testament by book ID.
     *
     * @param string $bookID The book ID.
     * @return mixed|null The testament if found, otherwise null.
     */
    public function findTestament($bookID)
    {
        $query = 'SELECT testament FROM bible_books WHERE bookId = :bookId LIMIT 1';
        return $this->databaseService->fetchSingleValue($query, [':bookId' => $bookID]);
    }

    /**
     * Finds the YouVersion book ID by book ID.
     *
     * @param string $bookID The book ID.
     * @return mixed|null The YouVersion book ID if found, otherwise null.
     */
    public function findUversionBookID($bookID)
    {
        $query = 'SELECT uversionBookID FROM bible_books WHERE bookId = :bookId LIMIT 1';
        return $this->databaseService->fetchSingleValue($query, [':bookId' => $bookID]);
    }

    /**
     * Gets detailed book information by language code and book name.
     *
     * @param string $languageCodeHL The language code.
     * @param string $bookName The name of the book.
     * @return array|null An associative array of book details if found, otherwise null.
     */
    public function getBookDetails($languageCodeHL, $bookName)
    {
        $query = 'SELECT bookId, bookName, bookNumber, testament, uversionBookID
              FROM bible_books
              WHERE languageCodeHL = :languageCodeHL AND name = :bookName LIMIT 1';
        $params = [
            ':languageCodeHL' => $languageCodeHL,
            ':bookName' => $bookName,
        ];
        return $this->databaseService->fetchRow($query, $params);
    }
}
