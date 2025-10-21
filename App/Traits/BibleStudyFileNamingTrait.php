<?php

namespace App\Traits;

use App\Repositories\LanguageRepository;
use App\Configuration\Config;

/**
 * Trait DbsFileNamingTrait
 *
 * Provides methods to generate standardized filenames for resources based on lesson identifiers,
 * language codes, and optional file extensions.
 *
 * @package App\Traits
 */
trait BibleStudyFileNamingTrait
{


    /**
     * Returns the prefix used in filenames.
     *
     * Override this method in implementing classes to customize the prefix.
     *
     * @return string The prefix for filenames.
     */
    protected function getFileNamePrefix(): string
    {
        return '';  // Default prefix; can be overridden in each specific controller.
    }

    /**
     * Generates a standardized filename based on the lesson identifier and language codes.
     *
     * @param string $lesson The lesson identifier (e.g., lesson number or name).
     * @param string $languageCodeHL1 The primary language code in HL format (e.g., 'eng00' for English).
     * @param string|null $languageCodeHL2 Optional secondary language code in HL format for bilingual filenames.
     * 
     * @return string The generated filename without extension.
     */
    public function getFileName(
        string $study,
        string $format,
        string $session,
        string $languageCodeHL1,
        string $languageCodeHL2 = null
    ): string {
        $studyName = Config::get("bible_study_names.$study", 'Unknown Study');
        $lang1 = $this->languageRepository->getEnglishNameForLanguageCodeHL($languageCodeHL1);

        if ($languageCodeHL2) {
            $lang2 = $this->languageRepository->getEnglishNameForLanguageCodeHL($languageCodeHL2);
            $fileName = "{$studyName}{$session}({$lang1}-{$lang2})";
        } else {
            $fileName = "{$studyName}{$session}({$lang1})";
        }
        if ($format == 'view') {
            $fileName .= '.html';
        } else if ($format == 'pdf') {
            $fileName .= '.pdf';
        }

        return str_replace(' ', '_', trim($fileName));
    }

    /**
     * Generates a filename with a `.pdf` extension.
     *
     * @param string $lesson The lesson identifier.
     * @param string $languageCodeHL1 The primary language code in HL format.
     * @param string|null $languageCodeHL2 Optional secondary language code for bilingual filenames.
     * 
     * @return string The generated filename with a `.pdf` extension.
     */
    public function getDir(string $study, string $format): string
    {
        if ($format == 'pdf') {
            $dir = Config::getDir('resources.bible_studies_pdf');
        } elseif ($format == 'view') {
            $dir = Config::getDir('resources.bible_studies_view');
        } else {
            $dir = Config::getDir('resources.bible_studies_other');
        }
        $dir .=  $study . '/';
        return $dir;
    }
    public function getStoragePath(string $study, string $format): string
    {
        if ($format == 'pdf') {
            $dir = Config::getDir('resources.bible_studies_pdf');
        } elseif ($format == 'view') {
            $dir = Config::getDir('resources.bible_studies_view');
        } else {
            $dir = Config::getDir('resources.bible_studies_other');
        }
        $dir .=  $study . '/';
        return $dir;
    }

    public function getUrl(string $study, string $format): string
    { {
            if ($format == 'pdf') {
                $dir = Config::getUrl('resources.bible_studies_pdf');
            } elseif ($format == 'view') {
                $dir = Config::getUrl('resources.bible_studies_view');
            } else {
                $dir = Config::getUrl('resources.bible_studies_other');
            }
            $dir .= '/' . $study . '/';
            return $dir;
        }
    }
}
