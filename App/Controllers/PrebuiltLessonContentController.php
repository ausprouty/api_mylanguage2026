<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Concerns\ValidatesArgs;
use App\Responses\ResponseBuilder;
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
            $languageCodeHL = $this->arg($args, 'languageCodeHL', [$this, 'normId']); 
            $study = $this->arg($args, 'study', [$this, 'normId']);
            $lesson = $this->arg($args, 'lesson', [$this, 'normId']);
         
            $output = $this->resolver->fetch($languageCodeHL, $study, $lesson);
            ResponseBuilder::ok()
                ->withMessage("Lesson content loaded.")
                ->withData($output)
                ->json(); // outputs and exits
        }  catch (Exception $e) {
            ResponseBuilder::error("Unexpected server error")
                ->withErrors(['exception' => $e->getMessage()])
                ->json(); // outputs and exits
        }
    }
}
