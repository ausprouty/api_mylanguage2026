<?php
declare(strict_types=1);

namespace App\Services\BibleStudy;

use App\Factories\BibleStudyReferenceFactory;
use App\Services\VideoService;
use App\Services\LoggerService;
use InvalidArgumentException;
use Throwable;

final class VideoJsonService
{
    public function __construct(
        private readonly VideoService $videoService,
        private readonly BibleStudyReferenceFactory $bibleStudyReferenceFactory,

    ) {}

    /**
     * Build a video block for a lesson.
     *
     * @return array{videoUrl: string|null}
     */
    public function generateVideoJsonBlock(
        string $study,
        int $lesson,
        string $languageCodeJF
    ): array {
        try {
            if ($study === '') {
                throw new InvalidArgumentException('Study must be provided.');
            }
            if ($lesson < 0) {
                throw new InvalidArgumentException('Lesson must be >= 0.');
            }
            if ($languageCodeJF === '') {
                throw new InvalidArgumentException('Language code is required.');
            }

            $ref = $this->bibleStudyReferenceFactory->createModel(
                $study,
                $lesson
            );

            $videoUrl = $this->videoService->getArclightUrl(
                $ref,
                $languageCodeJF
            );

            return ['videoUrl' => $videoUrl ?: null];
        } catch (Throwable $e) {
            LoggerService::logException(
                'VideoJsonService: generation failed',
                $e,
                [
                    'study'   => $study,
                    'lesson'  => $lesson,
                    'langJF'  => $languageCodeJF,
                ]
            );
            return ['videoUrl' => null];
        }
    }
}
