<?php

namespace App\Services\BibleStudy;

use App\Services\BibleStudy\BiblePassageJsonService;
use App\Services\BibleStudy\VideoJsonService;
use App\Services\LoggerService;

class LessonJsonService
{
    protected $biblePassageJsonService;
    protected $videoJsonService;

    public function __construct(
        BiblePassageJsonService $biblePassageJsonService,
        VideoJsonService $videoJsonService
    ) {
        $this->biblePassageJsonService = $biblePassageJsonService;
        $this->videoJsonService = $videoJsonService;
    }

    public function generateLessonJsonObject(
        $study,
        $lesson,
        $languageCodeHL,
        $languageCodeJF // ✅ removed trailing comma
    ): array {
         $t0 = microtime(true);
        // If you already pass rid in, use it. Otherwise generate one.
        $rid = substr(bin2hex(random_bytes(8)), 0, 12);
        try {
            $bibleOutput = $this->biblePassageJsonService->generateBiblePassageJsonBlock(
                $study, 
                $lesson, 
                $languageCodeHL
            );
            $t1 = microtime(true);
            $videoOutput = ['video'=> null];
            if ($languageCodeJF){
                $videoOutput = $this->videoJsonService->generateVideoJsonBlock(
                    $study, 
                    $lesson, 
                    $languageCodeJF
                );
                
            }
            $complete = true;
            $t2 = microtime(true);
            if (!$bibleOutput){
                $complete = false;
                LoggerService::logError ('LessonService-46', "No Bible Text for  $study / $lesson / $languageCodeHL");
            }
            $meta = [
               'meta' => [
                  'translationComplete' => $complete
               ]
            ];
            $mergedOutput = array_merge($bibleOutput, $videoOutput, $meta);
            $t3 = microtime(true);
            LoggerService::logTiming('LessonJsonServiceTiming', sprintf(
                'rid=%s bibleOutput=%s videoOutput=%.0fms Merge=%.0fms ',
                $rid,
                ($t1 - $t0) * 1000,
                ($t2 - $t1) * 1000,
                ($t3 - $t2) * 1000,
                $study, $lesson, $languageCodeHL, (string)$languageCodeJF
            ));
            return $mergedOutput; 
           
        } catch (\Exception $e) {
            throw new \Exception("Error generating Bible passage JSON block: " . $e->getMessage());
        }
    }
}
