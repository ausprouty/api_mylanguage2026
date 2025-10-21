<?php
declare(strict_types=1);

use function DI\autowire;
use function DI\get;
use function DI\factory;

use App\Configuration\Config;

// --- Contracts (tokens)
use App\Contracts\Templates\TemplateAssemblyService as TemplateAssemblyContract;
use App\Contracts\Translation\ProviderSelector as ProviderSelectorContract;
use App\Contracts\Translation\TranslationProvider as TranslationProviderContract;
use App\Contracts\Translation\TranslationService as TranslationServiceContract;

// --- Repositories & services
use App\Repositories\I18nStringsRepository;
use App\Repositories\I18nTranslationsRepository;
use App\Repositories\I18nClientsRepository;
use App\Repositories\I18nResourcesRepository;
use App\Repositories\LanguageRepository;

use App\Services\LoggerService;
use App\Services\BibleStudy\FsTemplateAssemblyService;
use App\Services\BibleStudy\TextBundleResolver;
use App\Services\Database\DatabaseService;

use App\Services\Language\I18nStringIndexer;
use App\Services\Language\I18nTranslationService;
use App\Services\Language\I18nTranslationQueueWorker;
use App\Services\Language\NullTranslationBatchService;
use App\Services\Language\GoogleTranslationBatchService;
use App\Services\Language\TranslationProviderSelector;
use App\Services\Language\TranslationBatchService;

// --- Cache
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

// Optional back-compat alias if an old FQCN still appears in code
use App\Contracts\Templates\TemplateAssemblyService as LegacyTemplateAssemblyContract;

// ---------- dynamic provider map ----------
Config::initialize();

$providerMap = [
    'null'   => NullTranslationBatchService::class,
    'google' => class_exists(GoogleTranslationBatchService::class)
        ? GoogleTranslationBatchService::class
        : TranslationBatchService::class, // fallback if Google class not present
    // 'deepl' => DeepLTranslationBatchService::class,
];

LoggerService::logDebug('32-translation.php', [
    'autoMtEnabled' => Config::get('i18n.autoMt.enabled', 'true'),
]);

return [

    // -------- i18n feature flags / config --------
    'i18n.autoMt.enabled'      => Config::get('i18n.autoMt.enabled', 'true'),
    'i18n.autoMt.allowGoogle'  => [], // e.g., ['eng00' => true]
    'i18n.baseLanguage'        => Config::get('i18n.baseLanguage', 'eng00'),

    // -------- core DB services --------
    DatabaseService::class     => autowire(DatabaseService::class),
    'db'                       => get(DatabaseService::class),
    PDO::class                 => factory(fn (DatabaseService $db) => $db->pdo()),

    // -------- cache (PSR-16) --------
    CacheInterface::class      => factory(fn () => new Psr16Cache(new ArrayAdapter())),

    // -------- template assembly (only keep if NOT already set in 20-contracts.php) --------
    //TemplateAssemblyContract::class       => autowire(FsTemplateAssemblyService::class),
    //LegacyTemplateAssemblyContract::class => get(TemplateAssemblyContract::class), // optional alias

    // -------- repositories --------
    I18nStringsRepository::class      => autowire(),
    I18nTranslationsRepository::class => autowire(),
    I18nClientsRepository::class      => autowire(),
    I18nResourcesRepository::class    => autowire(),
    LanguageRepository::class         => autowire(),

    // -------- translation provider selection --------
   ProviderSelectorContract::class => autowire(TranslationProviderSelector::class)
         ->constructorParameter('map', $providerMap),

    // Resolve Provider to the chosen concrete at runtime (single instance per container)
    TranslationProviderContract::class => factory(function (\Psr\Container\ContainerInterface $c) {
        /** @var ProviderSelectorContract $sel */
        $sel = $c->get(ProviderSelectorContract::class);
        $cls = $sel->chosenClass();
        return $c->get($cls);
    }),

    // Provider concretes
    GoogleTranslationBatchService::class => autowire(),
    NullTranslationBatchService::class   => autowire()->constructorParameter('prefixMode', true),
    TranslationBatchService::class       => autowire(), // generic fallback

    // -------- canonical Translation Service binding --------
       // Anywhere your code type-hints the contract (TranslationServiceContract),
    // it resolves to I18nTranslationService.
    TranslationServiceContract::class => autowire(I18nTranslationService::class)
         ->constructorParameter('baseLanguage', get('i18n.baseLanguage')),
 
    // -------- queue worker / indexer --------
    I18nStringIndexer::class => autowire(),

    I18nTranslationQueueWorker::class => autowire()
        ->constructorParameter(
            'autoMtEnabled',
            factory(function (\Psr\Container\ContainerInterface $c) {
                /** @var ProviderSelectorContract $sel */
+                $sel = $c->get(ProviderSelectorContract::class);
                // If the chosen provider is 'null', we consider auto-MT disabled.
                return $sel->chosenKey() !== 'null';
            })
        )
        ->constructorParameter('autoMtAllowGoogle', get('i18n.autoMt.allowGoogle')),

    // -------- resolver orchestrator --------
    TextBundleResolver::class => autowire()
        ->constructor(
            get(TemplateAssemblyContract::class),
            get(TranslationServiceContract::class),
            get(CacheInterface::class)
        ),

    // -------- optional aliases to keep old type-hints working --------
    // If any code still requests these, it will receive the same TranslationSvc instance.
    App\Services\Language\TranslationService::class     => get(TranslationServiceContract::class),
    App\Contracts\Translation\TranslationService::class => get(TranslationServiceContract::class),
 ];
