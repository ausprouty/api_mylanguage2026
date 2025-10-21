<?php

namespace App\Controllers\BibleStudy\Bilingual;

use \App\Models\BibleStudy\DbsReferenceModel;
use \App\Controllers\BibleStudy\DbsStudyController;

/**
 * Class BilingualDbsTemplateController
 *
 * Controller for managing DBS (Discovery Bible Study) templates in a bilingual format.
 * Extends BilingualStudyTemplateController to support functionalities specific to DBS studies.
 *
 * @package App\Controllers\BibleStudy\Bilingual
 */
class BilingualDbsTemplateController extends BilingualStudyTemplateController
{
    /**
     * Returns the prefix for filenames specific to DBS templates.
     *
     * @return string Prefix for DBS templates.
     */
    protected function getFileNamePrefix(): string {
        return 'DBS';
    }

    /**
     * Finds and returns the title for a DBS study based on the lesson and primary language code.
     *
     * @param string $lesson The lesson identifier.
     * @param string $languageCodeHL1 The primary language code for the title.
     * @return string The title of the DBS study.
     */
    protected function findTitle(string $lesson, string $languageCodeHL1): string {
        return DbsStudyController::getTitle($lesson, $languageCodeHL1);
    }

    /**
     * Retrieves the study reference information for a specific DBS lesson.
     * Uses DbsReferenceModel to fetch details for the lesson.
     *
     * @param string $lesson The lesson identifier.
     * @return DbsReferenceModel The study reference information for the DBS study.
     */
    protected function getStudyReferenceInfo(): DbsReferenceModel {
        $studyReferenceModel = 
        $this->bibleStudyReferenceFactory->createDbsReferenceModel($this->lesson);
        return $studyReferenceModel;
    }
    // I am not sure i need this.
    protected function getBiblePassageReferenceInfo(){

    }

    /**
     * Specifies the translation source for DBS templates.
     *
     * @return string Translation source identifier for DBS.
     */
    protected function getTranslationSource(): string {
        return 'dbs';
    }

    /**
     * Sets any unique template values specific to DBS studies.
     * This method is currently empty as there are no unique values required for DBS templates.
     */
    protected function setUniqueTemplateValues(): void {
        // No unique template values for this controller
    }



    
}
