<?php

namespace App\Controllers;

use App\Services\BibleStudy\LessonJsonService;
use App\Responses\ResponseBuilder;
use App\Helpers\ControllerValidator;
use App\Services\LoggerService;
use Exception;

class BibleStudyJsonController
{
    private LessonJsonService $studyService;

    public function __construct(LessonJsonService $studyService)
    {
        $this->studyService = $studyService;
    }

    public function webFetchLessonContent(array $args): void
    {
        try {
            $validated = ControllerValidator::validateArgs(
                $args,
                required: ['study', 'lesson', 'languageCodeHL'],
                optional: ['languageCodeJF'],
                casts: ['lesson' => 'int']
            );

            if ($validated === null) return; // error already output

            // Extract validated values
            $study = $validated['study'];
            $lesson = $validated['lesson'];
            $languageCodeHL = $validated['languageCodeHL'];
            $languageCodeJF = $validated['languageCodeJF'];

            // Build data blocks
            $output = $this->studyService->generateLessonJsonObject($study, $lesson, $languageCodeHL, $languageCodeJF);

            //LoggerService::logInfo('BibleStudyJsonController-43', print_r($output, true));
            ResponseBuilder::ok()
                ->withMessage("Lesson content loaded.")
                ->withData($output)
                ->json(); // outputs and exits
        } catch (Exception $e) {
            ResponseBuilder::error("Unexpected server error")
                ->withErrors(['exception' => $e->getMessage()])
                ->json(); // outputs and exits
        }
    }
}
