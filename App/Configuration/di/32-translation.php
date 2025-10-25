<?php
declare(strict_types=1);

// (translation engine + façade only)

use function DI\autowire;
use function DI\get;
use function DI\factory;

use Psr\Container\ContainerInterface;

// Contracts (new + legacy)
use App\Contracts\Language\TranslationProvider as TranslationProviderContract;
use App\Contracts\Language\ProviderSelector as ProviderSelectorContract;
use App\Contracts\Language\TranslationService as LangTranslationServiceContract;
use App\Contracts\Translation\TranslationService as LegacyTranslationServiceContract;

// Services
use App\Services\Language\I18nTranslationService;
use App\Services\Language\GoogleTranslationBatchService;
use App\Services\Language\NullTranslationBatchService;

return [

    // --- Provider concretes are buildable (no ctor params assumed here) ---
    GoogleTranslationBatchService::class => autowire(),
    NullTranslationBatchService::class   => autowire()
        ->constructorParameter('prefixMode', true), // keep if your Null expects it

    // --- Provider selection (single source of truth) ---
    'i18n.provider.map' => [
        'null'   => NullTranslationBatchService::class,
        'google' => GoogleTranslationBatchService::class,
    ],

    ProviderSelectorContract::class => factory(function (ContainerInterface $c) {
        /** @var array<string,string> $map */
        $map = $c->get('i18n.provider.map');

        $enabled   = $c->has('i18n.autoMt.enabled')  ? (bool)$c->get('i18n.autoMt.enabled')  : false;
        $requested = $c->has('i18n.autoMt.provider') ? (string)$c->get('i18n.autoMt.provider') : 'null';

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

    TranslationProviderContract::class => factory(function (ContainerInterface $c) {
        /** @var ProviderSelectorContract $sel */
        $sel = $c->get(ProviderSelectorContract::class);
        return $c->get($sel->chosenClass());
    }),

    // Back-compat: old class name resolves to the chosen provider
    App\Services\Language\TranslationBatchService::class =>
        get(TranslationProviderContract::class),

    // --- Canonical TranslationService (bind both contract namespaces) ---
    LangTranslationServiceContract::class =>
        autowire(I18nTranslationService::class)
            ->constructorParameter(
                'baseLanguage',
                factory(fn(ContainerInterface $c) =>
                    $c->has('i18n.baseLanguage') ? $c->get('i18n.baseLanguage') : 'eng00'
                )
            ),

    // Legacy namespace -> same service
    LegacyTranslationServiceContract::class =>
        get(LangTranslationServiceContract::class),
];
