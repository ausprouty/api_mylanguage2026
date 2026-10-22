<?php

namespace App\Controllers;

use App\Services\BibleStudy\BibleStudyService;

class BibleStudyController
{
    private BibleStudyService $studyService;

    public function __construct(BibleStudyService $studyService)
    {
        $this->studyService = $studyService;
    }

    /**
     * Entry point for web requests.
     * Extracts route arguments and delegates to getStudy().
     *
     * @param array $args The route arguments.
     * @return string The fetched study content.
     */
    public function webRequestToFetchStudy(array $args): string
    {
        $study = $args['study'];
        $format = $args['format'];
        $session = (int) $args['session'];
        $languageCodeHL1 = $args['language1'];
        $languageCodeHL2 = $args['language2'] ?? null;

        return $this->getStudy(
            $study,
            $format,
            $session,
            $languageCodeHL1,
            $languageCodeHL2
        );
    }

    /**
     * Fetch a Bible study based on study details.
     *
     * @param string      $study           The name of the study.
     * @param string      $format          The format (e.g., 'html', 'pdf').
     * @param int         $session         The session number.
     * @param string      $languageCodeHL1 Primary language.
     * @param string|null $languageCodeHL2 Optional secondary language.
     *
     * @return string The fetched study content.
     */
    public function getStudy(
        string $study,
        string $format,
        int $session,
        string $languageCodeHL1,
        ?string $languageCodeHL2 = null
    ): string {
        return $this->studyService->getStudy(
            $study,
            $format,
            $session,
            $languageCodeHL1,
            $languageCodeHL2
        );
    }
}
