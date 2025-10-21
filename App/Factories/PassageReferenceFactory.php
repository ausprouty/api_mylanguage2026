<?php
declare(strict_types=1);

namespace App\Factories;

use App\Models\Bible\PassageReferenceModel;
use App\Repositories\PassageReferenceRepository;
use App\Support\Caster;
use DASPRiD\Enum\NullValue;

/**
 * Factory for creating and populating PassageReferenceModel instances.
 */
class PassageReferenceFactory
{
    private $repository;

    private $entry;
    private $bookName;
    private $bookID;
    private $uversionBookID;
    private $bookNumber;
    private $testament;
    private $chapterStart;
    private $verseStart;
    private $chapterEnd;
    private $verseEnd;
    private $passageID;
    private $videoSource;
    private $videoPrefix;
    private $videoCode;
    private $videoSegment;
    private $startTime;
    private $endTime;

    /**
     * Constructor to initialize the repository dependency.
     */
    public function __construct(PassageReferenceRepository $repository)
    {
        $this->repository = $repository;
    }

    // study objects may have all we need for PassageReferenceModel

    public function createFromStudy($studyObject): PassageReferenceModel
    {
        if ($studyObject->getPassageID() === null) {
            return $this->createFromEntry($studyObject->getReference());
        } else {
            return $this->createFromStudyObject($studyObject);
        }
    }

    /**
     * Creates a model from a Study object.
     */
    public function createFromStudyObject($studyObject): PassageReferenceModel
    {
        $model = new PassageReferenceModel();
        $model->populate([
            'entry'         => $studyObject->getReference(),
            'bookName'      => $studyObject->getBookName(),
            'bookID'        => $studyObject->getBookID(),
            'uversionBookID'=> $studyObject->getUversionBookID(),
            'bookNumber'    => $studyObject->getBookNumber(),
            'testament'     => $studyObject->getTestament(),
            'chapterStart'  => $studyObject->getChapterStart(),
            'verseStart'    => $studyObject->getVerseStart(),
            'chapterEnd'    => $studyObject->getChapterEnd(),
            'verseEnd'      => $studyObject->getVerseEnd(),
            'passageID'     => $studyObject->getPassageID(),
            'videoSource'   => $studyObject->getVideoSource(),
            'videoPrefix'   => $studyObject->getVideoPrefix(),
            'videoCode'     => $studyObject->getVideoCode(),
            'videoSegment'  => $studyObject->getVideoSegment(),
            // Always cast to int seconds
            'startTime'     => Caster::toSecondsOrZero($studyObject->getStartTime()),
            'endTime'       => Caster::toSecondsOrZero($studyObject->getEndTime()),
        ]);
        return $model;
    }

    /**
     * Creates a model from an entry string and language code.
     */
    public function createFromEntry(
        string $entry,
        string $languageCodeHL = 'eng00'
    ): PassageReferenceModel {
        $model = new PassageReferenceModel();
        $this->entry = $this->checkEntrySpacing($entry);

        $this->bookName = $this->setBookName($entry);
        $this->setChapterAndVerses();
        $this->bookID         = $this->repository->findBookID($this->bookName, $languageCodeHL);
        $this->bookNumber     = $this->repository->findBookNumber($this->bookID);
        $this->testament      = $this->repository->findTestament($this->bookID);
        $this->uversionBookID = $this->repository->findUversionBookID($this->bookID);
        $this->passageID      = $this->bookID . '-' . $this->chapterStart . '-' . $this->verseStart . '-' . $this->verseEnd;

        $model->populate([
            'entry'         => $this->entry,
            'bookName'      => $this->bookName,
            'bookID'        => $this->bookID,
            'uversionBookID'=> $this->uversionBookID,
            'bookNumber'    => $this->bookNumber,
            'testament'     => $this->testament,
            'chapterStart'  => $this->chapterStart,
            'verseStart'    => $this->verseStart,
            'chapterEnd'    => $this->chapterStart,
            'verseEnd'      => $this->verseEnd,
            'passageID'     => $this->passageID,
            // No times in this path; if you add them later, cast with Caster::toSecondsOrZero
        ]);

        return $model;
    }

    /**
     * Creates a model from an import object.
     */
    public function createFromImport($import): PassageReferenceModel
    {
        $data = (array) $import;
        // Ensure start/end are always cast to int seconds
        if (\array_key_exists('startTime', $data)) {
            $data['startTime'] = Caster::toSecondsOrZero($data['startTime']);
        }
        if (\array_key_exists('endTime', $data)) {
            $data['endTime'] = Caster::toSecondsOrZero($data['endTime']);
        }

        $model = new PassageReferenceModel();
        $model->populate($data);
        return $model;
    }

    /**
     * Checks and adjusts entry spacing for consistency.
     */
    private function checkEntrySpacing(string $entry): string
    {
        $entry = trim($entry);
        if (strpos($entry, ' ') === false) {
            $firstNumber = mb_strlen($entry);
            for ($i = 0; $i <= 9; $i++) {
                $pos = mb_strpos($entry, (string) $i);
                if ($pos !== false && $pos < $firstNumber) {
                    $firstNumber = $pos;
                }
            }
            $book    = mb_substr($entry, 0, $firstNumber);
            $chapter = mb_substr($entry, $firstNumber);
            $entry   = $book . ' ' . $chapter;
        }
        return $entry;
    }

    /**
     * Creates a model from a PassageReferenceInfo array.
     */
    public function createFromPassageReferenceInfo(array $passageReferenceInfo): PassageReferenceModel
    {
        $model = new PassageReferenceModel();
        $model->populate([
            'entry'         => $this->checkEntrySpacing($passageReferenceInfo['entry'] ?? ''),
            'bookName'      => $passageReferenceInfo['bookName']      ?? null,
            'bookID'        => $passageReferenceInfo['bookId']        ?? null,
            'bookNumber'    => $passageReferenceInfo['bookNumber']    ?? null,
            'uversionBookID'=> $passageReferenceInfo['uversionBookID']?? null,
            'testament'     => $passageReferenceInfo['collection_code'] ?? '',
            'chapterStart'  => $passageReferenceInfo['chapterId']     ?? null,
            'verseStart'    => $passageReferenceInfo['verseStart']    ?? null,
            'chapterEnd'    => null,
            'verseEnd'      => $passageReferenceInfo['verseEnd']      ?? null,
            'passageID'     => $passageReferenceInfo['passageID']     ?? null,
            'videoSource'   => $passageReferenceInfo['videoSource']   ?? null,
            'videoPrefix'   => $passageReferenceInfo['videoPrefix']   ?? null,
            'videoCode'     => $passageReferenceInfo['videoCode']     ?? null,
            'videoSegment'  => (int) ($passageReferenceInfo['videoSegment'] ?? 0),
            // Always cast to int seconds (string or int input accepted)
            'startTime'     => Caster::toSecondsOrZero($passageReferenceInfo['startTime'] ?? null),
            'endTime'       => Caster::toSecondsOrZero($passageReferenceInfo['endTime']   ?? null),
        ]);
        return $model;
    }

    /**
     * Determines the book name from an entry.
     */
    private function setBookName(string $entry): string
    {
        $parts = explode(' ', $entry);
        $book  = $parts[0];
        if (in_array($book, ['1', '2', '3'], true) && isset($parts[1])) {
            $book .= ' ' . $parts[1];
        }
        if ($book === 'Psalm') {
            $book = 'Psalms';
        }
        return $book;
    }

    private function setChapterAndVerses(): void
    {
        $pass = str_replace($this->bookName, '', $this->entry);
        $pass = str_replace(' ', '', $pass);
        $pass = str_replace('፡', ':', $pass); // from Amharic
        $i = strpos($pass, ':');
        if ($i === false) {
            // this is the whole chapter
            $this->chapterStart = trim($pass);
            $this->verseStart   = 1;
            $this->verseEnd     = 999;
        } else {
            $this->chapterStart = substr($pass, 0, $i);
            $verses             = substr($pass, $i + 1);
            $i = strpos($verses, '-');
            if ($i !== false) {
                $this->verseStart = substr($verses, 0, $i);
                $this->verseEnd   = substr($verses, $i + 1);
            } else {
                $this->verseStart = $verses;
                $this->verseEnd   = $verses;
            }
        }
    }
}
