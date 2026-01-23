<?php
declare(strict_types=1);

namespace App\Services\BibleStudy;

use App\Services\BibleStudy\BiblePassageJsonService;
use App\Services\BibleStudy\VideoJsonService;
use App\Services\LoggerService;
use Exception;

/**
 * LessonJsonService
 *
 * Builds a single JSON-ready array for one lesson, merging:
 *
 *  - passage: Bible passage block (always attempted)
 *  - video:   Video block (only if JF language code is supplied)
 *  - meta:    Completion flags and other metadata
 *
 * Returned shape (high level):
 *
 * [
 *   'passage' => [
 *     'passageText'            => (string) HTML or plain text,
 *     'passageUrl'             => (string) URL to external passage (optional),
 *     'referenceLocalLanguage' => (string) Localized reference label
 *   ],
 *   'video' => ...,
 *   'meta' => [
 *     'translationComplete' => (bool)
 *   ]
 * ]
 */
class LessonJsonService
{
    private BiblePassageJsonService $biblePassageJsonService;
    private VideoJsonService $videoJsonService;

    public function __construct(
        BiblePassageJsonService $biblePassageJsonService,
        VideoJsonService $videoJsonService
    ) {
        $this->biblePassageJsonService = $biblePassageJsonService;
        $this->videoJsonService = $videoJsonService;
    }

    /**
     * @param string      $study
     * @param int|string  $lesson
     * @param string      $languageCodeHL
     * @param string|null $languageCodeJF
     *
     * @return array<string,mixed>
     */
    public function generateLessonJsonObject(
        $study,
        $lesson,
        $languageCodeHL,
        $languageCodeJF
    ): array {
        // Timing markers for logTimingSegments
        $t0 = microtime(true);

        // Request id for correlating logs inside this request
        $rid = substr(bin2hex(random_bytes(8)), 0, 12);

        try {
            // ------------------------------------------------------------
            // 1) Bible passage block
            // ------------------------------------------------------------
            // Expected shape:
            // [
            //   'passage' => [
            //     'passageText'            => (string),
            //     'passageUrl'             => (string),
            //     'referenceLocalLanguage' => (string)
            //   ]
            // ]
            $bibleOutput = $this->biblePassageJsonService
                ->generateBiblePassageJsonBlock(
                    $study,
                    $lesson,
                    $languageCodeHL
                );

            LoggerService::logDebug('LessonJsonService', [
                'rid' => $rid,
                'bibleOutput' => $bibleOutput,
            ]);

            $t1 = microtime(true);

            // ------------------------------------------------------------
            // 2) Video block (optional)
            // ------------------------------------------------------------
            // Default: explicit null block so the client can rely on the key.
            $videoOutput = ['video' => null];

            // Only attempt if JF code is provided and non-empty.
            $languageCodeJF = $languageCodeJF ? trim((string) $languageCodeJF) : '';
            if ($languageCodeJF !== '') {
                $videoOutput = $this->videoJsonService->generateVideoJsonBlock(
                    $study,
                    $lesson,
                    $languageCodeJF
                );
            }

            $t2 = microtime(true);

            // ------------------------------------------------------------
            // 3) Determine completeness (Bible passage present?)
            // ------------------------------------------------------------
            // “Complete” means we have *either* passage text or a URL to read it.
            // (URL-only is acceptable because the UI can send the user externally.)
            $passageText = '';
            $passageUrl = '';
            $referenceLocalLanguage = '';

            if (is_array($bibleOutput)) {
                $passage = is_array($bibleOutput['passage'] ?? null)
                    ? $bibleOutput['passage']
                    : [];

                $passageText = trim((string) ($passage['passageText'] ?? ''));
                $passageUrl = trim((string) ($passage['passageUrl'] ?? ''));
                $referenceLocalLanguage =
                    trim((string) ($passage['referenceLocalLanguage'] ?? ''));
            }

            // Keep this boolean logic simple and explicit:
            // - If BOTH are empty => incomplete
            // - Otherwise => complete
            $complete = !($passageText === '' && $passageUrl === '');

            if (!$complete) {
                LoggerService::logError(
                    'LessonJsonService',
                    "No Bible Text for $study / $lesson / $languageCodeHL",
                    [
                        'rid' => $rid,
                        'study' => $study,
                        'lesson' => $lesson,
                        'hl' => $languageCodeHL,
                    ]
                );
            }

            // If you want the client to always receive normalized passage strings
            // (even when BiblePassageJsonService omits them), you can enforce them:
            //
             $bibleOutput['passage'] = [
                 'passageText' => $passageText,
                 'passageUrl' => $passageUrl,
                 'referenceLocalLanguage' => $referenceLocalLanguage,
             ];

            // ------------------------------------------------------------
            // 4) Meta + merge
            // ------------------------------------------------------------
            $meta = [
                'meta' => [
                    'translationComplete' => $complete,
                ],
            ];

            // Note: array_merge on associative arrays will overwrite duplicate keys
            // using later values. That is desirable here: meta/video should win
            // if any collision (unlikely if you keep stable top-level keys).
            $mergedOutput = array_merge($bibleOutput, $videoOutput, $meta);

            LoggerService::logDebug('LessonJsonService', [
                'rid' => $rid,
                'mergedOutput' => $mergedOutput,
            ]);

            $t3 = microtime(true);

            // ------------------------------------------------------------
            // 5) Timing log
            // ------------------------------------------------------------
            LoggerService::logTimingSegments(
                'LessonJsonServiceTiming',
                $rid,
                [
                    'bibleOutput' => [$t0, $t1],
                    'videoOutput' => [$t1, $t2],
                    'merge'       => [$t2, $t3],
                ],
                [
                    'study' => $study,
                    'lesson' => $lesson,
                    'hl' => $languageCodeHL,
                    'jf' => $languageCodeJF,
                ]
            );

            return $mergedOutput;
        } catch (Exception $e) {
            // Keep the message accurate to the overall service (not only Bible)
            // and keep original exception as previous for stack traces.
            throw new Exception(
                'Error generating lesson JSON object: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
