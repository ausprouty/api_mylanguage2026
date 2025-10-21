<?php

namespace App\Controllers\BiblePassage\BibleBrain;

use App\Services\Bible\PassageFormatterService;
use App\Services\Web\BibleBrainConnectionService;
use App\Repositories\BibleReferenceRepository;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageReferenceModel;

class BibleBrainTextPlainController
{
    private $formatter;
    private $bibleReferenceRepository;
    private $response;
    private $referenceLocalLanguage;
    private $passageText;

    public function __construct(PassageFormatterService $formatter, BibleReferenceRepository $bibleReferenceRepository)
    {
        $this->formatter = $formatter;
        $this->bibleReferenceRepository = $bibleReferenceRepository;
    }

    public function fetchPassageData(
        BibleModel $bible,
        PassageReferenceModel $bibleReference
    ) {
        $url = sprintf(
            '/bibles/filesets/%s/%s/%s/?verse_start=%s&verse_end=%s',
            $bible->getExternalId(),
            $bibleReference->getBookId(),
            $bibleReference->getChapterStart(),
            $bibleReference->getVerseStart(),
            $bibleReference->getVerseEnd()
        );
      
        $this->response = (new BibleBrainConnectionService($url))->response;
       
        $this->passageText = $this->formatter->formatPassageText($this->response->data ?? []);
    }

    public function getPassageText()
    {
        return $this->passageText;
    }

    public function setReferenceLocalLanguage($bookId, $chapter, $verseStart, $verseEnd)
    {
        $bookName = $this->getBookNameLocalLanguage($bookId);
        $this->referenceLocalLanguage = "{$bookName} {$chapter}:{$verseStart}-{$verseEnd}";
    }

    public function getReferenceLocalLanguage()
    {
        return $this->referenceLocalLanguage;
    }

    private function getBookNameLocalLanguage($bookId)
    {
        return $this->response->data[0]->book_name_alt ?? $this->bibleReferenceRepository->getBookName($bookId);
    }
}
