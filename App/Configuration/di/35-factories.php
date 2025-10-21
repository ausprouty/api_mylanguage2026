<?php
declare(strict_types=1);

use function DI\autowire;
use function DI\create;
use function DI\get;

use DI\Container;

use App\Factories\BibleBrainConnectionFactory;
use App\Factories\BibleFactory;
use App\Factories\BibleStudyReferenceFactory;
use App\Factories\LanguageFactory;
use App\Factories\PassageFactory;
use App\Factories\PassageReferenceFactory;

use App\Repositories\BibleRepository;
use App\Repositories\LanguageRepository;
use App\Repositories\PassageReferenceRepository;

use App\Renderers\HtmlRenderer;
use App\Renderers\PdfRenderer;
use App\Renderers\RendererFactory;

use App\Services\Bible\BibleBrainLanguageService;
use App\Services\BibleStudy\BibleStudyService;

use App\Services\BibleStudy\BilingualStudyService;

use App\Services\BibleStudy\MonolingualStudyService;

use App\Services\BiblePassage\BiblePassageService;
use App\Services\Database\DatabaseService;
use App\Services\Language\TranslationService;
use App\Services\LoggerService;
use App\Services\QrCodeGeneratorService;
use App\Services\TemplateService;
use App\Services\TwigService;
use App\Services\VideoService;

return [

    // Factories
    BibleBrainConnectionFactory::class => autowire(),
    BibleFactory::class => autowire()
        ->constructor(get(BibleRepository::class)),
    BibleStudyReferenceFactory::class => autowire()
        ->constructor(
            get(DatabaseService::class),
            get(PassageReferenceRepository::class)
        ),
    LanguageFactory::class => autowire()
        ->constructor(get(DatabaseService::class)),
    PassageFactory::class => autowire(),
    PassageReferenceFactory::class => autowire()
        ->constructor(get(PassageReferenceRepository::class)),

    // Renderers + factory
    HtmlRenderer::class => autowire(),
    PdfRenderer::class  => autowire(),
    RendererFactory::class => create()->constructor([
        'html' => get(HtmlRenderer::class),
        'pdf'  => get(PdfRenderer::class),
    ]),

    // Core services/repositories
    LanguageRepository::class => autowire()
        ->constructor(
            get(DatabaseService::class),
            get(LanguageFactory::class)
        ),

    BibleBrainLanguageService::class => autowire(), // ctor is autowired

    // BibleStudy orchestrator + variants (autowire resolves deps)
    BibleStudyService::class => autowire()
        ->constructor(
            get(RendererFactory::class),
            get(LanguageRepository::class),
            get(DatabaseService::class),
            get(Container::class)
        ),

    // If your constructors are type-hinted, autowire is enough:

    BilingualStudyService::class           => autowire(),
    MonolingualStudyService::class         => autowire(),

    // Supporting services
    BiblePassageService::class => autowire(),
    TemplateService::class     => autowire(),
    TranslationService::class  => autowire(),
    TwigService::class         => autowire(),
    LoggerService::class       => autowire(),
    QrCodeGeneratorService::class => autowire(),
    VideoService::class        => autowire()
        ->constructor(get(TwigService::class)),
    DatabaseService::class     => autowire(),
];
