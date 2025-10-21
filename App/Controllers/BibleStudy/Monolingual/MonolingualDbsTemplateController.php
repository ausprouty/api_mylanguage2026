<?php

namespace App\Controllers\BibleStudy\Monolingual;

use App\Controllers\BibleStudy\DbsStudyController;
use App\Models\BibleStudy\DbsReferenceModel;
use App\Services\QrCodeGeneratorService;

class MonolingualDbsTemplateController extends MonolingualStudyTemplateController
{
    

    /**
     * Create a QR code for the given URL and language code.
     *
     * @param string $url The URL to encode in the QR code.
     * @param string $languageCodeHL The language code for the QR code.
     * @return string The URL of the generated QR code.
     */
    protected function createQrCode(string $url, string $languageCodeHL): string
    {
        $fileName = $this->getFileNamePrefix() . $this->lesson 
                  . '-' . $languageCodeHL . '.png';

        $this->qrCodeGeneratorService->initialize($url, 240, $fileName);
        $this->qrCodeGeneratorService->generateQrCode();

        return $this->qrCodeGeneratorService->getQrCodeUrl();
    }

    /**
     * Generate a study with the given lesson and language code.
     *
     * @param string $lesson The lesson identifier.
     * @param string $languageCodeHL The language code.
     */
    public function generateStudy($lesson, $languageCodeHL):string
    {
        $study = 'dbs';
       
    }

    /**
     * Find the title for a specific lesson and language code.
     *
     * @param string $lesson The lesson identifier.
     * @param string $languageCodeHL The language code.
     * @return string The title of the study.
     */
    protected function findTitle(string $lesson, string $languageCodeHL): string
    {
        return DbsStudyController::getTitle($lesson, $languageCodeHL);
    }

    /**
     * Get the prefix for file names related to the study.
     *
     * @return string The file name prefix.
     */
    protected function getFileNamePrefix(): string
    {
        return 'DBS';
    }

    /**
     * Get the monolingual PDF template name.
     *
     * @return string The name of the PDF template.
     */
    protected function getMonolingualPdfTemplateName(): string
    {
        return 'monolingualDbsPdf.twig';
    }

    /**
     * Get the monolingual view template name.
     *
     * @return string The name of the view template.
     */
    protected function getMonolingualViewTemplateName(): string
    {
        return 'monolingualDbsView.twig';
    }

    /**
     * Get the path prefix for study-related files.
     *
     * @return string The path prefix.
     */
    protected static function getPathPrefix(): string
    {
        return 'dbs';
    }

    /**
     * Retrieve the reference information for a specific lesson.
     *
     * @param string $lesson The lesson identifier.
     * @return DbsReferenceModel The study reference information.
     */
    protected function getStudyReferenceInfo(string $lesson): DbsReferenceModel
    {
        $studyReferenceInfo = new DbsReferenceModel();
        $studyReferenceInfo->setLesson($lesson);
        return $studyReferenceInfo;
    }

    /**
     * Get the translation source for the study.
     *
     * @return string The translation source.
     */
    protected function getTranslationSource(): string
    {
        return 'dbs';
    }

    /**
     * Set the file name for the study.
     */
    protected function setFileName(): void
    {
        $this->fileName = 'DBS' . $this->lesson . '(' 
                        . $this->language1->getName() . ')';
        $this->fileName = str_replace(' ', '_', $this->fileName);
    }

    /**
     * Define unique template values specific to the study.
     */
    protected function setUniqueTemplateValues(): void
    {
        // Add any DBS-specific values here if needed.
    }
}
