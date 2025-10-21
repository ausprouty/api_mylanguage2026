<?php
declare(strict_types=1);

use function DI\decorate;
use function DI\get;

// Controllers we must pin explicitly
use App\Controllers\BiblePassage\BibleBrain\BibleBrainTextPlainController;
use App\Controllers\BiblePassage\BibleBrain\BibleBrainLanguageController;

// Old-namespace shim classes (concrete subclasses you added)
use App\Services\Bible\PassageFormatterService as OldFormatterService;
use App\Services\Bible\BibleBrainLanguageService as OldBbLangService;

// Repo expected by TextPlain controller
use App\Repositories\BibleReferenceRepository;

// Legacy bare-name aliases
use App\Controllers\Language\BilingualTemplateTranslationController as LangBilingualCtrl;
use App\Controllers\Language\MonolingualTemplateTranslationController as LangMonolingualCtrl;

use App\Repositories\LanguageRepository;

return [

    // ORDER-PROOF PIN: Always return a controller built with the exact types required,
    // regardless of any other autowire definition elsewhere.
    BibleBrainTextPlainController::class => decorate(
        function ($previous, $c) {
            return new BibleBrainTextPlainController(
                $c->get(OldFormatterService::class),
                $c->get(BibleReferenceRepository::class)
            );
        }
    ),

    BibleBrainLanguageController::class => decorate(
        function ($previous, $c) {
            return new BibleBrainLanguageController(
                $c->get(LanguageRepository::class),
                $c->get(OldBbLangService::class)
            );
        }
    ),

    // Legacy bare-name aliases
    'BilingualTemplateTranslationController'   => get(LangBilingualCtrl::class),
    'MonolingualTemplateTranslationController' => get(LangMonolingualCtrl::class),
];
