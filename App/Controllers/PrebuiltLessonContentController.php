<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Concerns\ValidatesArgs;
use App\Responses\JsonResponse;
use App\Services\BibleStudy\PrebuiltLessonContentResolver;
use App\Services\LoggerService;
use App\Support\i18n\Normalize;
use Exception;

final class PrebuiltLessonContentController
{
    use ValidatesArgs;

    private bool $debugController = true;

    public function __construct(private PrebuiltLessonContentResolver $resolver) {}

    /**
     * Args:
     * - languageCodeHL
     * - study
     * - lesson
     */
    public function webFetch(array $args): void
    {
        try {
            $lang = $this->arg($args, 'languageCodeHL', [$this, 'normId']); 
            $study = $this->arg($args, 'study', [$this, 'normId']);
            $lesson = $this->arg($args, 'lesson', [$this, 'normId']);
         
            $res = $this->resolver->fetch($lang, $study, $lesson);

            if ($this->debugController){
                LoggerService::logInfoJson('PrebuiltLessonContentController-65', $res['data']['meta']);
            }
            JsonResponse::success($res['data'], $headers, 200);
        } catch (Exception $e) {
            JsonResponse::error($e->getMessage());
        }
    }
}
