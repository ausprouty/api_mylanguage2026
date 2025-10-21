<?php

namespace App\Controllers\BibleStudy\Bilingual;

use App\Repositories\BibleRepository;
use App\Repositories\LanguageRepository;
use App\Services\QrCodeGeneratorService;
use App\Traits\DbsFileNamingTrait;
use App\Traits\TemplatePlaceholderTrait;
use App\Controllers\BibleStudy\BibleBlockController;
use App\Configuration\Config;
use App\Models\Language\LanguageModel;
use App\Services\Database\DatabaseService;
use App\Factories\BibleStudyReferenceFactory;
use App\Factories\BibleFactory;

/**
 * Class BilingualStudyTemplateController
 *
 * This abstract controller provides a foundation for managing bilingual Bible study templates.
 * It includes methods for setting filenames, generating QR codes, creating Bible text blocks,
 * and handling template placeholders. The QR code generation leverages a dedicated service,
 * `QrCodeGeneratorService`, for more modular functionality.
 *
 * @package App\Controllers\BibleStudy\Bilingual
 */
abstract class BilingualStudyTemplateController
{
    use \App\Traits\DbsFileNamingTrait;
    use \App\Traits\TemplatePlaceholderTrait;


    protected DatabaseService $databaseService;
    protected LanguageRepository $languageRepository;
    protected BibleRepository $bibleRepository;
    protected BibleStudyReferenceFactory $bibleStudyReferenceFactory;
    protected QrCodeGeneratorService $qrCodeService;
    protected BibleBlockController $bibleBlockController;
    protected BibleFactory  $bibleFactory;
    protected string $fileName;
    protected string $bibleBlock;
    protected string $qrcode1;
    protected string $qrcode2;
    protected string $lesson;
    protected $language1;
    protected $language2;
    protected $studyReferenceInfo;
    protected $bible1;
    protected $bible2;
    protected $biblePassage1;
    protected $biblePassage2;
    protected $bibleReference;

    // get information about Study
    abstract protected function getStudyReferenceInfo();
    // get bible passage and all related passage info
    abstract protected function getBiblePassageReferenceInfo();


    public function __construct(
        BibleBlockController $bibleBlockController,
        BibleRepository $bibleRepository,
        BibleStudyReferenceFactory $bibleStudyReferenceFactory,
        LanguageRepository $languageRepository,
        QrCodeGeneratorService $qrCodeService
    ) {
        $this->bibleBlockController = $bibleBlockController;
        $this->bibleRepository = $bibleRepository;
        $this->bibleStudyReferenceFactory = $bibleStudyReferenceFactory;
        $this->languageRepository = $languageRepository;
        $this->qrCodeService = $qrCodeService;
        $this->bibleFactory = new BibleFactory($this->bibleRepository);
    }
    // this will create a Language Object based on the languageCodeHl
    public function setLanguages(string $languageCodeHL1, string $languageCodeHL2)
    {
        $this->language1 =
            $this->languageRepository->findOneLanguageByLanguageCodeHL($languageCodeHL1);
       
        $this->language2 =
            $this->languageRepository->findOneLanguageByLanguageCodeHL($languageCodeHL2);
       
   
    }
    public function setLesson(string $lesson)
    {
        $this->lesson = $lesson;
        $this->studyReferenceInfo = $this->getStudyReferenceInfo();
       
    }
    public function setBibles()
    {
        $this->bible1 = $this->getBible($this->language1->getLanguageCodeHl());
       
        $this->bible2 = $this->getBible($this->language2->getLanguageCodeHl());
       
        flush();
    }
    public function getBible($languageCodeHL)
    {
        return $this->bibleFactory->createFromLanguageCodeHL($languageCodeHL);
    }
    public function setBiblePassages()
    {
        $this->biblePassage1 = $this->getBiblePassage($this->studyReferenceInfo, $this->language1);
        $this->biblePassage2 = $this->getBiblePassage($this->studyReferenceInfo, $this->language2);
    }
    // study reference model can be any of 
    public function getBiblePassage($studyReferenceInfo, LanguageModel $language) {}


    protected function createBibleBlock(): void
    {
        if ($this->biblePassage1->getPassageText() && $this->biblePassage2->getPassageText()) {
            $this->bibleBlockController->load(
                $this->biblePassage1->getPassageText(),
                $this->biblePassage2->getPassageText(),
                $this->bibleReference->getVerseRange()
            );
            $this->bibleBlock = $this->bibleBlockController->getBlock();
        } else {
            $this->createBibleBlockWhenTextMissing();
        }
    }

    private function createBibleBlockWhenTextMissing(): void
    {
        $this->bibleBlock = $this->showTextOrLink($this->biblePassage1);
    }

    protected function createQrCodeForPassage(string $url, string $languageCode): string
    {
        $fileName = $this->getFileNamePrefix() . $this->lesson . '-' . $languageCode . '.png';
        $this->qrCodeService->initialize($url, 240, $fileName);
        $this->qrCodeService->generateQrCode();

        return $this->qrCodeService->getQrCodeUrl();
    }

    protected abstract function getFileNamePrefix(): string;

    protected function generateQrCodes(): void
    {
        $this->qrcode1 = $this->createQrCodeForPassage($this->biblePassage1->getPassageUrl(), $this->language1->getLanguageCodeHL());
        $this->qrcode2 = $this->createQrCodeForPassage($this->biblePassage2->getPassageUrl(), $this->language2->getLanguageCodeHL());
    }

    private function showTextOrLink($biblePassage): string
    {
        return $biblePassage->getPassageText() === null
            ? $this->showDivLink($biblePassage)
            : $this->showDivText($biblePassage);
    }

    private function showDivLink($biblePassage): string
    {
        $templatePath = Config::getDir('resources.templates') . 'bibleBlockDivLink.twig';
        $template = file_get_contents($templatePath);

        $existing = ['{{dir_language}}', '{{url}}', '{{Bible Reference}}', '{{Bid}}'];
        $new = [
            $biblePassage->getBibleDirection(),
            $biblePassage->passageUrl,
            $biblePassage->referenceLocalLanguage,
            $biblePassage->getBibleBid()
        ];
        return str_replace($existing, $new, $template);
    }

    private function showDivText($biblePassage): string
    {
        $templatePath = Config::getDir('resources.templates') . 'bibleBlockDivText.twig';
        $template = file_get_contents($templatePath);

        $existing = ['{{dir_language}}', '{{url}}', '{{Bible Reference}}', '{{Bid}}', '{{passage_text}}'];
        $new = [
            $biblePassage->getBibleDirection(),
            $biblePassage->passageUrl,
            $biblePassage->referenceLocalLanguage,
            $biblePassage->getBibleBid(),
            $biblePassage->getPassageText()
        ];
        return str_replace($existing, $new, $template);
    }

    protected function setFileName(): void
    {
        $this->fileName = $this->generateFileName(
            $this->getFileNamePrefix(),
            $this->lesson,
            $this->language1->getLanguageCodeHL(),
            $this->language2->getLanguageCodeHL()
        );
    }
}
