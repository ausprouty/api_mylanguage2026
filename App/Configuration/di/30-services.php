<?php
declare(strict_types=1);

use function DI\autowire;
use function DI\create;
use function DI\get;

use Psr\SimpleCache\CacheInterface;

use App\Configuration\Config;
use App\Contracts\Templates\TemplatesRootProvider as TemplatesRootProviderContract;
use App\Infra\ConfigTemplatesRootProvider;

// HTTP
use App\Http\HttpClientInterface;

// Factories
use App\Factories\BibleBrainConnectionFactory;
use App\Factories\BibleGatewayConnectionFactory;
use App\Factories\BibleWordConnectionFactory;
use App\Factories\YouVersionConnectionFactory;

// Repositories
use App\Repositories\LanguageRepository;
use App\Repositories\BibleBrainBibleRepository;
use App\Repositories\BiblePassageRepository;
use App\Repositories\NullBiblePassageRepository;
use App\Repositories\PassageReferenceRepository;
use App\Repositories\BibleReferenceRepository;

// Models
use App\Models\Bible\PassageReferenceModel;

// Services (canonical)
use App\Services\BiblePassage\PassageFormatterService;
use App\Services\BiblePassage\BibleBrainPassageService;
use App\Services\BiblePassage\BibleGatewayPassageService;
use App\Services\BiblePassage\BibleWordPassageService;
use App\Services\BiblePassage\YouVersionPassageService;

use App\Services\LoggerService;

return [

    // Core
    Config::class => autowire(),
    LoggerService::class => autowire(),

    CacheInterface::class => function (): CacheInterface {
        if (class_exists(\App\Infra\SimpleArrayCache::class)) return new \App\Infra\SimpleArrayCache();
        if (class_exists(\Symfony\Component\Cache\Simple\ArrayCache::class)) return new \Symfony\Component\Cache\Simple\ArrayCache();
        if (class_exists(\App\Infra\NullCache::class)) return new \App\Infra\NullCache();
        throw new \RuntimeException('No CacheInterface implementation available.');
    },

    // Contracts

    TemplatesRootProviderContract::class => autowire(ConfigTemplatesRootProvider::class),

    // Factories
    BibleBrainConnectionFactory::class => autowire()
        ->constructor(get(HttpClientInterface::class), get(LoggerService::class)),
    BibleGatewayConnectionFactory::class => autowire()
        ->constructor(get(HttpClientInterface::class), get(LoggerService::class)),
    BibleWordConnectionFactory::class => autowire()
        ->constructor(get(HttpClientInterface::class), get(LoggerService::class)),
    YouVersionConnectionFactory::class => autowire()
        ->constructor(get(HttpClientInterface::class), get(LoggerService::class)),

    // Repositories (no aliasing between these two!)
    LanguageRepository::class => autowire(),
    BibleBrainBibleRepository::class => autowire(),

    BiblePassageRepository::class => autowire(NullBiblePassageRepository::class),

    // Resolve each to itself; BibleReferenceRepository composes PassageReferenceRepository
    PassageReferenceRepository::class => autowire(),
    BibleReferenceRepository::class   => autowire(), // DI will inject PassageReferenceRepository into its constructor

    // Legacy string key
    'PassageReferenceModel' => get(PassageReferenceModel::class),

    // Services
    PassageFormatterService::class => autowire(),
  

    // Positional pin: (BibleBrainConnectionFactory, LoggerService)
    BibleBrainPassageService::class => create(BibleBrainPassageService::class)
        ->constructor(
            get(BibleBrainConnectionFactory::class),
            get(LoggerService::class)
        ),

    BibleGatewayPassageService::class => autowire(),
    BibleWordPassageService::class    => autowire(),
    YouVersionPassageService::class   => autowire(),
];
