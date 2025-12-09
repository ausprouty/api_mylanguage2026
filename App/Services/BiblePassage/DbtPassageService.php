<?php

namespace App\Services\BiblePassage;



/**
 * DbtPassageService returns links to  https://live.bible.is/

*/
class DbtPassageService extends AbstractBiblePassageService
{
   
    public function __construct(){

    }

   /**
     * Get the URL for the passage.
     * Subclasses must implement this method.
     *
     * @return string The URL for the passage.
     */
    /**
     * Example:https://live.bible.is/bible/MYABJB/LUK/1
     */
    public function getPassageUrl(): string
    {
        $reference = trim((string) $this->passageReference->getEntry());
        $version   = trim((string) $this->bible->getExternalId());

        if ($reference === '' || $version === '') {
            throw new InvalidArgumentException(
                'Missing reference or version.'
            );
        }

        $baseUrl = Config::get('endpoints.dbt') ?? 'https://live.bible.is';
        $baseUrl = rtrim((string) $baseUrl, '/');

        return $baseUrl . '/' . $version . '/' . $reference;
    }


    /**
     * Get the webpage content for the passage.
     * Subclasses must implement this method.
     *
     * @return  array<string,mixed>|string.
     */
     public function getWebPage(): array|string
     {
        return ' ';
     }

    /**
     * Get the text of the Bible passage.
     * Subclasses must implement this method.
     *
     * @return string The text of the passage.
     */
     public function getPassageText(): string{
        return ' ';
     }

    /**
     * Get the local language reference for the passage.
     * Subclasses must implement this method.
     *
     * @return string The local language reference.
     */
     public function getReferenceLocalLanguage(): string;{
         $first = $this->webpage[0] ?? null;
        $book  = is_array($first) ? ($first['book_name_alt'] ?? null) : ($first->book_name_alt ?? null);
        $verses =  $this->passageReference->getChapterStart().':'
                . $this->passageReference->getVerseStart().'-'
                . $this->passageReference->getVerseEnd();

        if ($book) {
            return $book.' '. $verses;
               
        }
        return $verses;
     }

}
