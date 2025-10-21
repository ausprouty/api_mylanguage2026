<?php

namespace App\Services\BibleStudy;

use App\Configuration\Config;
use App\Factories\BibleStudyReferenceFactory;
use App\Factories\PassageReferenceFactory;
use App\Models\Bible\BibleModel;
use App\Models\Language\LanguageModel;
use App\Models\BibleStudy\DbsReferenceModel;

use App\Models\Bible\PassageModel;
use App\Models\Video\VideoModel;
use App\Repositories\BibleRepository;
use App\Repositories\LanguageRepository;
use App\Services\BiblePassage\BiblePassageService;
use App\Services\BibleStudy\TemplateService;
use App\Services\Database\DatabaseService;
use App\Services\Language\TranslationService;
use Symfony\Component\String\AbstractString;
use App\Services\TwigService;
use App\Services\LoggerService;
use App\Services\QRCodeGeneratorService;
use App\Services\VideoService;
use Mpdf\Tag\TextArea;

/**
 * Abstract class for Bible Study services.
 * Provides a template for retrieving study, language, and Bible information.
 */
abstract class AbstractBibleStudyService
{
    protected $study;
    protected $format;
    protected $language;
    protected $lesson;
    protected $languageCodeHL1;
    protected $languageCodeHL2;

    protected $primaryLanguage;
    protected $primaryBible;
    protected $primaryBiblePassage;
    protected $primaryVideoUrl;
    protected $studyTemplateName;
    protected $bibleTemplateName;
    protected $videoTemplateName;
    protected $twigTranslation1;
    protected $qrcode1;

    protected $studyReferenceInfo;
    protected $passageReferenceInfo;

    protected $databaseService;
    protected $languageRepository;
    protected $bibleRepository;
    protected $biblePassageService;
    protected $bibleStudyReferenceFactory;
    protected $passageReferenceFactory;
    protected $templateService;
    protected $translationService;
    protected $twigService;
    protected $loggerService;
    protected $qRCodeGeneratorService;
    protected $videoService;
    protected $bibleStudyDetails;

    /**
     * Fillin Template with Twig
     *
     * @return String
     */
    abstract function assembleOutput(): string;

    /**
     * Retrieve language information.
     *
     * @return LanguageModel
     */
    abstract function getLanguageInfo(): LanguageModel;

    /**
     * Retrieve Bible information.
     *
     * @return BibleModel
     */
    abstract function getBibleInfo(): BibleModel;

    /**
     * Retrieve Bible text.
     *
     * @return array
     */
    abstract function getPassageModel(): PassageModel;

    /**
     * Retrieve the name of template for the study format.
     *
     * @param string $format The desired format.
     * @return string
     */
    abstract function getStudyTemplateName(string $study, string $format): void;
    /**
     * Retrieve the name of template for the VideoTemplate.
     *
     * @param string $format The desired format.
     * @return string
     */
    abstract function getVideoTemplateName(?string $videoUrl, string $format): void;


    /**
     * Retrieve translation for Twig template.
     *
     * @return string
     */
    abstract function getTwigTranslationArray(): array;

    /**
     * Constructor for dependency injection.
     *
     * @param DatabaseService $databaseService Database service instance.
     * @param LanguageRepository $languageRepository Language repository instance.
     * @param BibleRepository $bibleRepository Bible repository instance.
     * @param BibleStudyReferenceFactory $bibleStudyReferenceFactory Study reference factory.
     * @param BiblePassageService $biblePassageService Bible passage service.
     * @param PassageReferenceFactory $passageReferenceFactory Passage reference factory.
     * @param TemplateService $templateService Template service instance.
     * @param TranslationService $translationService Translation service instance.
     */
    public function __construct(
        DatabaseService $databaseService,
        LanguageRepository $languageRepository,
        BibleRepository $bibleRepository,
        BibleStudyReferenceFactory $bibleStudyReferenceFactory,
        BiblePassageService $biblePassageService,
        PassageReferenceFactory $passageReferenceFactory,
        TemplateService $templateService,
        TranslationService $translationService,
        TwigService  $twigService,
        LoggerService  $loggerService,
        QRCodeGeneratorService $qRCodeGeneratorService,
        VideoService $videoService
    ) {
        $this->databaseService = $databaseService;
        $this->languageRepository = $languageRepository;
        $this->bibleRepository = $bibleRepository;
        $this->bibleStudyReferenceFactory = $bibleStudyReferenceFactory;
        $this->biblePassageService = $biblePassageService;
        $this->passageReferenceFactory = $passageReferenceFactory;
        $this->templateService = $templateService;
        $this->translationService = $translationService;
        $this->twigService  = $twigService;
        $this->loggerService = $loggerService;
        $this->qRCodeGeneratorService = $qRCodeGeneratorService;
        $this->videoService = $videoService;
    }

    /**
     * Generate the study output.
     *
     * @param string $study The study type.
     * @param string $format The output format.
     * @param int $lesson The lesson number.
     * @param string $languageCodeHL1 Primary language code.
     * @param string|null $languageCodeHL2 Secondary language code (optional).
     * @return array The generated study output.
     */
    public function generate(
        $study,
        $format,
        $lesson,
        $languageCodeHL1,
        $languageCodeHL2 = null
    ): string {
        try {
            $this->initializeParameters($study, $format, $lesson, $languageCodeHL1, $languageCodeHL2);
            $this->loadLanguageAndBibleInfo();
            $this->prepareReferences();
            print_r('finished prepReferences, ');
            $this->selectTemplatesAndTranslation();
            print_r('selectTemplatesAndTranslation, ');
            $this->checkProgress(); // This could throw an exception
            print_r('checkProgress, ');
            $test = $this->assembleOutput();
            print_r('and assembleOutput ');
            return $test;
        } catch (\InvalidArgumentException $e) {
            // Handle specific validation errors
            $this->loggerService->logError('Validation error in class AbstractBibleStudyService', ['message' => $e->getMessage()]);
            return 'Validation error: ' . $e->getMessage();
        } catch (\Exception $e) {
            // Handle unexpected errors
            $this->loggerService->logError('Unexpected error', ['message' => $e->getMessage()]);
            return 'An unexpected error occurred in AbstractBibleStudyService generating your study.'.  $e->getMessage();
        }
    }



    /**
     * Initialize study parameters.
     *
     * @param string $study The study type.
     * @param string $format The output format.
     * @param int $lesson The lesson number.
     * @param string $languageCodeHL1 Primary language code.
     * @param string|null $languageCodeHL2 Secondary language code (optional).
     */
    private function initializeParameters(
        $study,
        $format,
        $lesson,
        $languageCodeHL1,
        $languageCodeHL2
    ): void {
        $this->validateParameters($study, $format, $lesson);

        $this->study = $study;
        $this->format = $format;
        $this->lesson = $lesson;
        $this->languageCodeHL1 = $languageCodeHL1;
        $this->languageCodeHL2 = $languageCodeHL2;
    }

    /**
     * Validate the provided parameters.
     *
     * @param string $study The study type.
     * @param string $format The output format.
     * @param int $lesson The lesson number.
     * @throws \InvalidArgumentException If any parameter is invalid.
     */
    private function validateParameters($study, $format, $lesson): void
    {
        if (empty($study) || empty($format) || empty($lesson)) {
            throw new \InvalidArgumentException(
                'Study, format, and lesson must all be provided.'
            );
        }
    }

    /**
     * Load primary language and Bible information.
     *
     * @throws \RuntimeException If language or Bible info cannot be loaded.
     */
    private function loadLanguageAndBibleInfo(): void
    {
        try {
            $this->primaryLanguage = $this->getLanguageInfo();
            $this->primaryBible = $this->getBibleInfo();
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Error loading language or Bible information: ' .
                    $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Prepare study and passage references.
     *
     * @throws \Exception If reference preparation fails.
     */
    private function prepareReferences(): void
    {
        try {
            $this->studyReferenceInfo = $this->getStudyReferenceInfo();
            $this->passageReferenceInfo = $this->passageReferenceFactory
                ->createFromStudy($this->studyReferenceInfo);
            $this->primaryBiblePassage = $this->getPassageModel();
            // we need to get the language code for the video first
            $languageCodeJF = $this->primaryLanguage->getLanguageCodeJF();
            // now set the Url for the primary Video
            $this->primaryVideoUrl = $this->getVideoUrl($this->passageReferenceInfo, $languageCodeJF);
            print_r ('this is the primaryVideoUrl<br>');
            print_r($this->primaryVideoUrl);
        } catch (\Exception $e) {
            error_log('Reference preparation failed: ' . $e->getMessage());
            throw $e;
        }
    }
    public function getVideoUrl($passageReferenceInfo, $languageCodeJF){
        if (!$passageReferenceInfo->getVideoCode()){
            return null;
        }
        $videoUrl = $this->videoService->getUrl($passageReferenceInfo, $languageCodeJF);
        return $videoUrl;
    }

    public function getStudyReferenceInfo(): DbsReferenceModel|LifePrincipleReferenceModel|LeadershipReferenceModel
    {

        return  $this->bibleStudyReferenceFactory->createModel($this->study, $this->lesson);
    }

    /**
     * Build the study template and translation.
     *
     * @throws \RuntimeException If template or translation fails.
     */
    private function selectTemplatesAndTranslation(): void
    {
        try {
            if ($this->format == 'pdf'){
                $this->getQrCode();
            }
            $this->getStudyTemplateName($this->study, $this->format);
            $this->getVideoTemplateName($this->primaryVideoUrl, $this->format);
            if ($this->format == 'pdf'){
                $this->bibleTemplateName = 'bibleBlockNormalPdf.twig';
            }
            else {
                $this->bibleTemplateName = 'bibleBlockNormalView.twig';
            }
            $this->twigTranslation1 = $this->getTwigTranslationArray();
            
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Error building template or translation: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
    // URL of QR code takes you to Bible passage
    private function getQrCode(){
        $url = $this->primaryBiblePassage->getPassageUrl();
        $fileName = ucFirst($this->study);
        $fileName .= $this->studyReferenceInfo->getLesson();
        $fileName .= '-'. $this->languageCodeHL1 . '.png';
        $this->qrcode1 = $fileName;
        $this->qRCodeGeneratorService->confirmCodeExists($url, $fileName, $size = 320);

    }
    

    /**
     * Assemble the final study output.
     *
     * @return array The assembled output.
     * @throws \InvalidArgumentException If any parameter is missing or blank.
     */
    private function checkProgress(): array
    {
        $requiredFields = [
            'study_template' => $this->studyTemplateName ?? null,
            'translation' => $this->twigTranslation1 ?? null,
            'language' => $this->primaryLanguage->getLanguageCodeHL() ?? null,
            'bible' => $this->primaryBible->getVolumeName() ?? null,
        ];

        foreach ($requiredFields as $key => $value) {
            if (empty($value)) {
                throw new \InvalidArgumentException("Missing or blank parameter: $key");
            }
        }

        return [
            'status' => 'success',
            'data' => $requiredFields,
        ];
    }
}
