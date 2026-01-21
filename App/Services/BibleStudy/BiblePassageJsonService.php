<?php
declare(strict_types=1);

namespace App\Services\BibleStudy;

use App\Factories\BibleStudyReferenceFactory;
use App\Factories\PassageReferenceFactory;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Models\BibleStudy\StudyReferenceModel;
use App\Models\Language\LanguageModel;
use App\Repositories\BibleRepository;
use App\Repositories\LanguageRepository;
use App\Services\BiblePassage\BiblePassageService;
use App\Services\LoggerService;
use InvalidArgumentException;
use Throwable;

final class BiblePassageJsonService
{
    private string $study;
    private int $lesson;
    private string $languageCodeHL;
    private ?LanguageModel $primaryLanguageModel = null;
    private ?BibleModel $primaryBibleModel = null;
    
    /** @var array<string,mixed> */
    private ?StudyReferenceModel $studyReferenceModel = null;

    /** @var array<string,mixed> */
    private ?PassageReferenceModel $passageReferenceModel = null;

     /** @var array<string,mixed> */
    private ?PassageModel $passageModel = null;

    public array $primaryBiblePassagePayload = [];

    public function __construct(
        private BiblePassageService $biblePassageService,
        private BibleRepository $bibleRepository,
        private BibleStudyReferenceFactory $bibleStudyReferenceFactory,
        private LanguageRepository $languageRepository,
        private PassageReferenceFactory $passageReferenceFactory,
    ) {}

    /**
     * Build a bible passage block for a lesson.
     *
     * @return array{
     *   passage: array<string,mixed>|PassageModel|null,
     * }
     */
    public function generateBiblePassageJsonBlock(
        string $study,
        int $lesson,
        string $languageCodeHL
    ): array {
        try {
            $t0 = microtime(true);
            // If you already pass rid in, use it. Otherwise generate one.
            $rid = substr(bin2hex(random_bytes(8)), 0, 12);
            $this->initialize($study, $lesson, $languageCodeHL);
            $t1 = microtime(true);

            $this->loadLanguageAndBible();
            $t2 = microtime(true);

            $this->loadPassageRefs();
            $t3 = microtime(true);

            $this->loadBibleText();
            $t4 = microtime(true); 

            $block = $this->makeBlock();
            $t5 = microtime(true);

          LoggerService::logTimingSegments(
                'BiblePassageJsonServiceTiming',
                $rid,
                [
                    'initialize'        => [$t0, $t1],
                    'loadLanguageBible' => [$t1, $t2],
                    'loadPassageRefs'   => [$t2, $t3],
                    'loadBibleText'     => [$t3, $t4],
                    'makeBlock'         => [$t4, $t5],
                ],
                [
                    'study' => $study,
                    'lesson' => $lesson,
                    'hl' => $languageCodeHL,
                ]
            );

            return $block;
            
        } catch (Throwable $e) {
            LoggerService::logException(
                'BiblePassageJsonService: generation failed',
                $e,
                [
                    'study'   => $study,
                    'lesson'  => $lesson,
                    'langHL'  => $languageCodeHL,
                ]
            );

            return [
                'passage'  => null,
            ];
        }
    }

    private function initialize(
        string $study,
        int $lesson,
        string $languageCodeHL
    ): void {
        if ($study === '') {
            throw new InvalidArgumentException('Study must be provided.');
        }
        if ($lesson < 0) {
            throw new InvalidArgumentException('Lesson must be >= 0.');
        }
        if ($languageCodeHL === '') {
            throw new InvalidArgumentException('Language code is required.');
        }

        $this->study = $study;
        $this->lesson = $lesson;
        $this->languageCodeHL = $languageCodeHL;
    }

    private function loadLanguageAndBible(): void
    {
        $t0 = microtime(true);
        $rid = substr(bin2hex(random_bytes(8)), 0, 12);
        $this->primaryLanguageModel =
            $this->languageRepository
                 ->findOneLanguageByLanguageCodeHL($this->languageCodeHL);
        $t1 = microtime(true);
        
        LoggerService::logDebug(
            'LanguageModel',
            'state',
            ['model' => $this->primaryLanguageModel->toArray()]
        );
        $t2 = microtime(true);

        $this->primaryBibleModel = $this->bibleRepository
            ->findBestBibleByLanguageCodeHL($this->languageCodeHL);
        $t3 = microtime(true);
        
        LoggerService::logDebug(
            'BibleModel',
            'state',
            [[
                'hl' => $this->languageCodeHL,
                'isNull' => $this->primaryBibleModel === null,
                'model' => $this->primaryBibleModel
                    ? $this->primaryBibleModel->toArray()
                    : null,
            ]]
        );
        $t4 = microtime(true);

        if ($this->primaryBibleModel === null) {
            throw new InvalidArgumentException(
                'No primary Bible found for language ' . $this->languageCodeHL
            );
        }
        LoggerService::logDebug('BiblePassageJsonService - loadLanguageAndBible - Timing', sprintf(
                'rid=%s primaryLanguageModel=%s LoggerService=%.0fms 
                primaryBibleModel=%.0fms LoggerService=%.0fms  ',
                $rid,
                ($t1 - $t0) * 1000,
                ($t2 - $t1) * 1000,
                ($t3 - $t2) * 1000,
                ($t4 - $t3) * 1000
            ));
    }

    private function loadPassageRefs(): void
    {
        // e.g., returns model
        $this->studyReferenceModel = $this->bibleStudyReferenceFactory
            ->createModel($this->study, $this->lesson);
        LoggerService::logDebug(
            'StudyReferenceModel',
            'state',
            ['model' => $this->studyReferenceModel->toArray()]
        );

        // Derive concrete passage range(s) for this lesson.
        $this->passageReferenceModel = $this->passageReferenceFactory
            ->createFromStudy($this->studyReferenceModel);
        LoggerService::logDebug(
            'PassageReferenceModel',
            'state',
            ['model' => $this->passageReferenceModel->toArray()]
        );
    }

    /* Returns object with the following
    {
       "bpid": "1259-Luke-7-36-50",
        "dateChecked": "2025-08-14",
        "dateLastUsed": "2025-10-03",
        "passageText": "<div class=\"passage-text\">        "passageUrl": "https://biblegateway.com/passage/?search=Luke%207:36–50&version=NIVUK",
        "referenceLocalLanguage": "Luke 7:36-50 New International Version - UK",
        "timesUsed": 83
    }
   
}

    */

    private function loadBibleText(): void
    {
        $this->passageModel = $this->biblePassageService->getPassage(
             $this->primaryBibleModel,
             $this->passageReferenceModel
        );
         LoggerService::logDebug(
            'PassageModel',
            'state',
            ['model' => $this->passageModel->toArray()]
        );
    }

    /**
     * @return array{
     *   passage: array<string,mixed>|PassageModel|null,
     * }
     */
    private function makeBlock(): array
    {
        return [
            'passage'  => [
                'passageText'            => $this->passageModel->getPassageText(),
                'passageUrl'             => $this->passageModel->getPassageUrl(),
                'referenceLocalLanguage' => $this->passageModel->getReferenceLocalLanguage()
            ]
        ];
    }
}
