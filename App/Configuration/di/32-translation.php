<?php
declare(strict_types=1);

// (translation engine + façade only)

use function DI\autowire;
use function DI\get;
use function DI\factory;

use Psr\Container\ContainerInterface;

use App\Configuration\Config;

// Contracts (new + legacy)
use App\Contracts\Translation\TranslationProvider as TranslationProviderContract;
use App\Contracts\Translation\ProviderSelector as ProviderSelectorContract;
use App\Contracts\Translation\TranslationService as TranslationServiceContract;


// Services
use App\Services\Language\I18nTranslationService;
use App\Services\Language\GoogleTranslationBatchService;
use App\Services\Language\NullTranslationBatchService;
use App\Services\Language\TranslationProviderSelector;

return [

    // --- Provider concretes are buildable (no ctor params assumed here) ---
    GoogleTranslationBatchService::class => autowire(),
    NullTranslationBatchService::class   => autowire()
        ->constructorParameter('prefixMode', true), // keep if your Null expects it

    // --- Provider selector driven by Config::get(...) ---
    ProviderSelectorContract::class =>
        autowire(TranslationProviderSelector::class)
            ->constructor(
                [
                    'google' => GoogleTranslationBatchService::class,
                    'null'   => NullTranslationBatchService::class,
                ],
                static function (string $key, $default = null) {
                    // Single source of truth for env + i18n.autoMt.*
                    return Config::get($key, $default);
                }
            ),

    // Resolve the provider via the selector (what TQP asks for)
    TranslationProviderContract::class => factory(
        static function (ContainerInterface $c) {
            /** @var ProviderSelectorContract $sel */
            $sel = $c->get(ProviderSelectorContract::class);
            $cls = method_exists($sel, 'resolveProviderClass')
                ? $sel->resolveProviderClass()
                : $sel->chosenClass();
            return $c->get($cls);
        }
    ),

    // Concrete providers (normal autowiring)
    GoogleTranslationBatchService::class => autowire(),
    NullTranslationBatchService::class   => autowire(),

    // Canonical TranslationService binding
    TranslationServiceContract::class =>
        autowire(I18nTranslationService::class)
            ->constructorParameter(
                'baseLanguage',
                factory(
                    static fn (ContainerInterface $c) =>
                        Config::get('i18n.baseLanguage', 'eng00')
                )
            ),
 ];
