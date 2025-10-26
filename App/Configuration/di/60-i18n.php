<?php

// (i18n repositories + config only — no provider/service bindings)
declare(strict_types=1);

use function DI\autowire;
use function DI\get;
use function DI\factory;


// Repositories
use App\Repositories\I18nStringsRepository;
use App\Repositories\I18nTranslationsRepository;
use App\Repositories\I18nClientsRepository;
use App\Repositories\I18nResourcesRepository;
use App\Repositories\LanguageRepository;

// If you centralise config keys here, expose them (otherwise remove these)
return [

    // ---- Config keys (optional centralisation) ----
    // Provide defaults if env-specific files don’t set them
    'i18n.baseLanguage'    => 'eng00',
    'i18n.autoMt.enabled'  => false,
    'i18n.autoMt.provider' => 'null',
    // Keep your allowGoogle array wherever you prefer; exposing here is fine:
    // [] => allow all; ['fr','ar'] => only those
    'i18n.autoMt.allowGoogle' => [],

    // ---- Repositories (need PDO) ----
    // NOTE: bind PDO once in 30-services.php using DatabaseService->getPdo()
    I18nStringsRepository::class       => autowire()
        ->constructorParameter('pdo', get(\PDO::class)),
    I18nTranslationsRepository::class  => autowire()
        ->constructorParameter('pdo', get(\PDO::class)),
    I18nClientsRepository::class       => autowire()
        ->constructorParameter('pdo', get(\PDO::class)),
    I18nResourcesRepository::class     => autowire()
        ->constructorParameter('pdo', get(\PDO::class)),

    // LanguageRepository likely needs DatabaseService or PDO; wire as you do elsewhere
    LanguageRepository::class          => autowire(),
];
