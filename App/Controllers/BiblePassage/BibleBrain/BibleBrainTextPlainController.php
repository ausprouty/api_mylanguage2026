<?php

namespace App\Controllers\BiblePassage\BibleBrain;

use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Repositories\BibleReferenceRepository;
use App\Services\Bible\PassageFormatterService;
use App\Services\LoggerService;
use App\Services\Web\BibleBrainConnectionService;

class BibleBrainTextPlainController
{
    public function __construct(
        private BibleModel $bible,
        private PassageReferenceModel $bibleReference,
        private PassageFormatterService $formatter,
        private BibleReferenceRepository $bibleReferenceRepository
    ) {
    }

    /**
     * Fetch passage from BibleBrain (text_plain), format it,
     * and return a hydrated PassageModel.
     *
     * No saving. No numeral localisation.
     */
    public function fetchPassage(): PassageModel
    {
        $passage = new PassageModel();
        $passage->setBpid($this->bibleReference->getBpid());

        $endpoint = sprintf(
            '/api/bibles/filesets/%s/%s/%s/',
            $this->bible->getExternalId(),
            $this->bibleReference->getBookId(),
            $this->bibleReference->getChapterStart()
        );

        $query = [
            'verse_start' => (string) $this->bibleReference->getVerseStart(),
            'verse_end'   => (string) $this->bibleReference->getVerseEnd(),
        ];

        $conn = new BibleBrainConnectionService($endpoint, $query);
        $response = $conn->response ?? null;

        if (!$response || empty($response->data)) {
            LoggerService::logError(
                'BBTPC:empty_response',
                $url
            );
            return $passage;
        }

        $html = $this->formatter->formatPassageText($response->data ?? []);
        if ($html === null || $html === '') {
            LoggerService::logError(
                'BBTPC:no_text',
                $url
            );
            return $passage;
        }

        $passage->setPassageText($html);

        $localRef = $this->buildReferenceLocalLanguage($response);
        $passage->setReferenceLocalLanguage($localRef);

        $passage->setPassageUrl($url);

        return $passage;
    }

    /**
     * Build the local-language reference string like:
     *   "يوحنا 3:16-18"
     * using book_name_alt when available.
     */
    private function buildReferenceLocalLanguage(object $response): string
    {
        $bookId    = $this->bibleReference->getBookId();
        $chapter   = $this->bibleReference->getChapterStart();
        $verseFrom = $this->bibleReference->getVerseStart();
        $verseTo   = $this->bibleReference->getVerseEnd();

        $bookName = null;

        if (isset($response->data[0]->book_name_alt)
            && $response->data[0]->book_name_alt !== ''
        ) {
            $bookName = $response->data[0]->book_name_alt;
        } else {
            $bookName = $this->bibleReferenceRepository->getBookName($bookId);
        }

        if ($verseFrom === $verseTo) {
            return sprintf('%s %s:%s', $bookName, $chapter, $verseFrom);
        }

        return sprintf(
            '%s %s:%s-%s',
            $bookName,
            $chapter,
            $verseFrom,
            $verseTo
        );
    }
}
