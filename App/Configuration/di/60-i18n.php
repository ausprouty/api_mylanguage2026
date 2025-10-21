<?php
declare(strict_types=1);

use function DI\autowire;
use function DI\get;


use App\Contracts\Templates\TemplateAssemblyService as TemplateAssemblyContract;
use App\Contracts\Templates\TemplatesRootProvider as TemplatesRootProviderContract;
use App\Contracts\Translation\TranslationService as TranslationServiceContract;

use App\Infra\ConfigTemplatesRootProvider;

use App\Repositories\I18nStringsRepository;
use App\Repositories\I18nTranslationsRepository;

use App\Services\BibleStudy\TextBundleResolver;
use App\Services\BibleStudy\FsTemplateAssemblyService; // << keep in BibleStudy
use App\Services\Database\DatabaseService;
use App\Services\Language\TranslationService as TranslationConcrete; // your shim/impl

use Psr\SimpleCache\CacheInterface;
// ---- Pick ONE cache concrete and stick to it ----
// Option A (your custom):
use App\Support\Cache\ArrayCache as CacheConcrete;
// Option B (alternative):
// use App\Support\Cache\ArrayCache as CacheConcrete;

return [
    // ── Base values ────────────────────────────────────────────────────────
    'i18n.baseLanguage'        => 'eng00',
    'i18n.autoMt.enabled'      => false,
    'i18n.autoMt.allowGoogle'  => [],

    // ── Core services ──────────────────────────────────────────────────────
    DatabaseService::class => autowire(DatabaseService::class),
    'db'                   => get(DatabaseService::class),

     // ── i18n repositories ─────────────────────────────────────────────────
    I18nStringsRepository::class      => autowire(),
    I18nTranslationsRepository::class => autowire(),

    // Single PSR-16 cache binding (no duplicates)
    CacheInterface::class  => autowire(CacheConcrete::class),


    // Contracts → concretes (using your aliases)
    TemplateAssemblyContract::class      => \DI\autowire(FsTemplateAssemblyService::class),
    TranslationServiceContract::class    => \DI\autowire(TranslationConcrete::class),
    TemplatesRootProviderContract::class => \DI\autowire(ConfigTemplatesRootProvider::class),
    // Resolver can be plain autowired (constructor is fully type-hinted)
    TextBundleResolver::class            => \DI\autowire(TextBundleResolver::class),
 
    // ── Namespace drift aliases ───────────────────────────────────────────
    'App\Services\TemplateService' => get(BibleStudyTemplateService::class),

    'App\Services\Bible\BibleBrainLanguageService'
        => get(App\Services\BiblePassage\BibleBrainLanguageService::class),

    'App\Services\Bible\YouVersionPassageService'
        => get(App\Services\BiblePassage\YouVersionPassageService::class),

    // Some spots reference an unqualified string key "BibleReferenceRepository"
    'BibleReferenceRepository'
        => get(App\Repositories\PassageReferenceRepository::class),

    App\Repositories\BibleReferenceRepository::class
        => get(App\Repositories\PassageReferenceRepository::class),

    'App\Cron\BibleBrainLanguageSyncService'
        => get(App\Services\BibleBrain\BibleBrainLanguageSyncService::class),
    'App\Cron\BibleBrainBibleSyncService'
        => get(App\Services\BibleBrain\BibleBrainBibleSyncService::class),
    'App\Cron\BibleBrainBibleCleanupService'
        => get(App\Services\BibleBrain\BibleBrainBibleCleanupService::class),
];
