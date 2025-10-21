<?php
declare(strict_types=1);

use function DI\factory;
use function DI\get;

use Psr\Container\ContainerInterface;

// Controllers we build explicitly
use App\Controllers\BiblePassage\BibleBrain\BibleBrainTextPlainController;
use App\Controllers\BiblePassage\BibleBrain\BibleBrainLanguageController;

// Old-namespace shim classes (you added these subclasses)
use App\Services\Bible\PassageFormatterService as OldFormatterService;
use App\Services\Bible\BibleBrainLanguageService as OldBbLangService;

// Exact repo type required by TextPlain controller (our composition wrapper)
use App\Repositories\BibleReferenceRepository;
use App\Repositories\PassageReferenceRepository;

// Other deps
use App\Repositories\LanguageRepository;
use App\Factories\BibleBrainConnectionFactory;
use App\Services\LoggerService;

// Legacy bare-name aliases
use App\Controllers\Language\BilingualTemplateTranslationController as LangBilingualCtrl;
use App\Controllers\Language\MonolingualTemplateTranslationController as LangMonolingualCtrl;

return [

    // BibleBrainTextPlainController(formatter, bibleReferenceRepository)
    BibleBrainTextPlainController::class => factory(
        function (ContainerInterface $c): BibleBrainTextPlainController {
            $formatter = $c->get(OldFormatterService::class);
            // Build the wrapper explicitly to guarantee type:
            $bibleRefRepo = new BibleReferenceRepository(
                $c->get(PassageReferenceRepository::class)
            );
            return new BibleBrainTextPlainController($formatter, $bibleRefRepo);
        }
    ),

    // BibleBrainLanguageController(languageRepository, old-ns language service)
    BibleBrainLanguageController::class => factory(
        function (ContainerInterface $c): BibleBrainLanguageController {
            $langRepo = $c->get(LanguageRepository::class);
            // Instantiate the OLD namespace class explicitly to avoid aliasing
            $oldSvc = new OldBbLangService(
                $c->get(BibleBrainConnectionFactory::class),
                $c->get(LoggerService::class)
            );
            return new BibleBrainLanguageController($langRepo, $oldSvc);
        }
    ),

    // Legacy bare-name aliases
    'BilingualTemplateTranslationController'   => get(LangBilingualCtrl::class),
    'MonolingualTemplateTranslationController' => get(LangMonolingualCtrl::class),
];
