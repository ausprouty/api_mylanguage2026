<?php

namespace App\Controllers\BibleStudy;

use App\Services\BibleStudy\TitleService;
use App\Responses\JsonResponse;

class StudyTitleController
{
    protected $titleService;

    public function __construct(TitleService $titleService)
    {
        $this->titleService = $titleService;
    }

    public function webGetTitleForStudy($args): void
    {
        $study = $args['study'];
        $languageCodeHL = $args['languageCodeHL'];
        $titleData = $this->titleService->getTitleAndLessonNumber($study, $languageCodeHL);
        $output = [
            'title' => $titleData['title'],
            'lessonNumber' => $titleData['lessonNumber']
        ];
        JsonResponse::success($output);
    }
}
