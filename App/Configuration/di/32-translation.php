<?php
declare(strict_types=1);

use function DI\autowire;
use function DI\get;
use function DI\factory;

use Psr\Container\ContainerInterface;

// ===== Translation provider + service wiring (no TemplateAssembly here) =====
use App\Contracts\Language\TranslationProvider as TranslationProviderContract;
use App\Contracts\Language\ProviderSelector as ProviderSelectorContract;
use App\Contracts\Language\TranslationService as LangTranslationServiceContract;
use App\Contracts\Translation\TranslationService as LegacyTranslationServiceContract;

use App\Services\Language\I18nTranslationService;
use App\Services\Language\GoogleTranslationBatchService;
use App\Services\Language\NullTranslationBatchService;

return [

    // --- Providers are buildable (no ctor params assumed) ---
    GoogleTranslationBatchService::class => autowire(),
    NullTranslationBatchService::class   => autowire()
        ->constructorParameter('prefixMode', true), // keep if your Null provider expects it

    // Map of provider keys to concrete classes (no legacy names here)
    'i18n.provider.map' => [
        'null'   => NullTranslationBatchService::class,
        'google' => GoogleTranslationBatchService::class,
    ],

    // Choose a provider once (global). All config keys default safely if missing.
    ProviderSelectorContract::class => factory(function (ContainerInterface $c) {
        /** @var array<string,string> $map */
        $map = $c->get('i18n.provider.map');

        $enabled = $c->has('i18n.autoMt.enabled')
            ? (bool) $c->get('i18n.autoMt.enabled')
            : false;

        $requested = $c->has('i18n.autoMt.provider')
            ? (string) $c->get('i18n.autoMt.provider')   // 'google' | 'null'
            : 'null';

        $googleAvailable = class_exists(GoogleTranslationBatchService::class);

        $key = (!$enabled)
            ? 'null'
            : (($requested === 'google' && $googleAvailable) ? 'google' : 'null');

        $cls = $map[$key] ?? NullTranslationBatchService::class;

        return new class($cls) implements ProviderSelectorContract {
            public function __construct(private string $cls) {}
            public function chosenClass(): string { return $this->cls; }
        };
    }),

    // Resolve provider contract → chosen concrete
    TranslationProviderContract::class => factory(function (ContainerInterface $c) {
        /** @var ProviderSelectorContract $sel */
        $sel = $c->get(ProviderSelectorContract::class);
        return $c->get($sel->chosenClass());
    }),

    // Back-compat: old class name resolves to the chosen provider
    App\Services\Language\TranslationBatchService::class =>
        get(TranslationProviderContract::class),

    // Canonical translation service
    LangTranslationServiceContract::class =>
        autowire(I18nTranslationService::class)
            ->constructorParameter(
                'baseLanguage',
                factory(fn(ContainerInterface $c) =>
                    $c->has('i18n.baseLanguage') ? $c->get('i18n.baseLanguage') : 'eng00'
                )
            ),

    // Back-compat for the alternate/legacy contract namespace
    LegacyTranslationServiceContract::class =>
        get(LangTranslationServiceContract::class),
];
