<?php
declare(strict_types=1);

use function DI\autowire;
use function DI\get;

use Psr\SimpleCache\CacheInterface;

use App\Contracts\Templates\TemplateAssemblyService as TemplateAssemblyContract;
use App\Contracts\Templates\TemplatesRootProvider as TemplatesRootProviderContract;
use App\Contracts\Translation\TranslationService as TranslationServiceContract;


use App\Infra\ConfigTemplatesRootProvider;
use App\Infra\NullCache;

use App\Services\BibleStudy\TextBundleResolver;
use App\Services\Templates\TemplateAssemblyService as TemplateAssemblyConcrete;

return [
    // Contract → Concrete: fixes “class is not instantiable” for TemplateAssemblyService
    TemplateAssemblyContract::class => autowire(TemplateAssemblyConcrete::class),
 
    // Root provider
    TemplatesRootProviderContract::class => autowire(ConfigTemplatesRootProvider::class),
 

    // Translation (prefer container param 'translation.base_lang', else eng00)
    TranslationServiceContract::class => function (\Psr\Container\ContainerInterface $c) {
        $base = 'eng00';
        if ($c->has('translation.base_lang')) {
            $base = (string) $c->get('translation.base_lang');
        } elseif ($c->has('app.config.language.base_hl')) {
            // keep legacy support for your earlier config key
            $base = (string) $c->get('app.config.language.base_hl');
        }
        return new App\Services\Language\NullTranslationService($base);
    },

    // Cache (override in prod.php to APCu, Redis, etc.)
    CacheInterface::class => autowire(NullCache::class),

    // Text bundle resolver (single canonical binding)
    TextBundleResolver::class => autowire()
        ->constructor(
            get(TemplateAssemblyContract::class),
            get(TranslationServiceContract::class),
            get(CacheInterface::class)
        ),
];
