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
        $t0 = microtime(true);
         // Optional: add a request id to correlate logs
        $rid = substr(bin2hex(random_bytes(8)), 0, 12);
        try {
            $t1 = microtime(true);
            $validated = ControllerValidator::validateArgs(
                $args,
                required: ['study', 'lesson', 'languageCodeHL'],
                optional: ['languageCodeJF'],
                casts: ['lesson' => 'int']
            );
            $t2 = microtime(true);

            if ($validated === null) return; // error already output

            // Extract validated values
            $study = $validated['study'];
            $lesson = $validated['lesson'];
            $languageCodeHL = $validated['languageCodeHL'];
            $languageCodeJF = $validated['languageCodeJF'];
            $t3 = microtime(true);

            // Build data blocks
            $output = $this->studyService->generateLessonJsonObject($study, $lesson, $languageCodeHL, $languageCodeJF);
            $t4 = microtime(true);
             // Emit timing headers BEFORE json() outputs
            $validateMs = ($t2 - $t1) * 1000;
            $extractMs  = ($t3 - $t2) * 1000;
            $serviceMs  = ($t4 - $t3) * 1000;
            $totalMs    = (microtime(true) - $t0) * 1000;

            // Optional: log the same numbers server-side
            LoggerService::logTiming('BibleStudyJsonController', sprintf(
                'rid=%s validate=%.0fms extract=%.0fms service=%.0fms total=%.0fms study=%s lesson=%s hl=%s jf=%s',
                $rid, $validateMs, $extractMs, $serviceMs, $totalMs,
                (string)$study, (string)$lesson, (string)$languageCodeHL, (string)$languageCodeJF
            ));

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
