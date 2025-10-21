<?php

namespace App\Controllers;

use App\Services\BibleStudy\BibleStudyService;

class BibleStudyController {
    /**
     * @var StudyService
     */
    private $studyService;

    /**
     * Constructor for BibleStudyController.
     *
     * @param StudyService $studyService The service responsible for fetching Bible studies.
     */
    public function __construct(BibleStudyService $studyService) {
        $this->studyService = $studyService;
    }

    /**
     * Entry point for web requests. Extracts arguments from the route and delegates to `handleFetch`.
     *
     * @param array $args The route arguments.
     * @return string The fetched study content.
     */
    public function webRequestToFetchStudy(array $args): string {
        // Extract variables from the route arguments
        $study = $args['study'];
        $format = $args['format'];
        $session = (int) $args['session'];
        $languageCodeHL1 = $args['language1'];
        $languageCodeHL2  = $args['language2'] ?? null;

        // Delegate to the internal method
        return $this->getStudy($study, $format, $session,  $languageCodeHL1, $languageCodeHL2);
    }

    /**
     * Fetch a Bible study based on study details.
     *
     * @param string      $study     The name of the study.
     * @param string      $format    The format (e.g., 'html', 'pdf').
     * @param int         $session   The session number.
     * @param string      $language1 The primary language of the study.
     * @param string|null $language2 Optional secondary language for bilingual studies.
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
        // Delegate the work to the StudyService with the correct parameter order
        return $this->studyService->getStudy(
            $study, 
            $format, 
            $session, 
            $languageCodeHL1, 
            $languageCodeHL2
        );
    }
    
}
