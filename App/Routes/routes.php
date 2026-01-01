<?php

use FastRoute\RouteCollector;
use App\Configuration\Config;
use App\Responses\JsonResponse;
use App\Services\LoggerService;
use App\Http\Handlers\PostHandler;

return function (RouteCollector $r): void {
    // Normalize basePath to: "" or "/something" (no trailing slash)
    $rawBase = (string) (Config::get('base_path') ?? '');
    $basePath = rtrim('/' . ltrim($rawBase, '/'), '/');
    if ($basePath === '/') {
        $basePath = '';
    }

    LoggerService::logInfo(
        'router.basePath',
        ['raw' => $rawBase, 'normalized' => $basePath]
    );

    $container = require __DIR__ . '/../Configuration/container.php';
    $postHandler = new PostHandler($container);

    // Call a controller method from the DI container (fast to type).
    $call = static function (string $class, string $method) use ($container) {
        return static function ($args) use ($container, $class, $method) {
            return $container->get($class)->{$method}($args);
        };
    };

    // Convenience wrapper for POST controllers that use PostHandler.
    $post = static function (string $class) use ($postHandler) {
        return $postHandler->make($class);
    };

    // root
    $r->addRoute('GET', '/',
        static function (): void {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo json_encode([
                'ok'      => true,
                'service' => 'api2.mylanguage.net.au',
                'time'    => (new DateTimeImmutable(
                    'now',
                    new DateTimeZone('UTC')
                ))->format(DateTimeInterface::RFC3339),
            ]);
        }
    );

    // Minimal debug endpoint
    $r->addRoute('GET', '/api_mylanguage2026/_debug/ping2',
        static function () {
            header('Content-Type: text/plain');
            echo 'OK literal';
            return null;
        }
    );

    // test
    $r->addGroup($basePath . '/api/test',
        function (RouteCollector $g) use ($call) {
            $g->addRoute('GET', '',
                $call(
                    \App\Controllers\TestBibleBrainController::class,
                    'logFiveLanguages'
                ));
        }
    );

    // version 2 Available
    $r->addGroup($basePath . '/api/v2/available',
        function (RouteCollector $g) use ($post) {
            $g->addRoute('POST', '/bibles',
                $post(\App\Controllers\Bibles\BiblesAvailableController::class));

            $g->addRoute('POST', '/languages',
                $post(\App\Controllers\Language\LanguagesAvailableController::class));
        }
    );

    // version 2 Bible
    $r->addGroup($basePath . '/api/v2/bible',
        function (RouteCollector $g) use ($post) {
            $g->addRoute('POST', '/passage',
                $post(\App\Controllers\BiblePassage\PassageRetrieverController::class));
        }
    );

    // version 2 Translate
    $r->addGroup($basePath . '/api/v2/translate',
        function (RouteCollector $g) use ($call) {
            $g->addRoute('GET', '/cron/{token}',
                $call(\App\Controllers\TranslationQueueController::class, '__invoke'));

            // GET /api/v2/translate/text/{kind}/{subject}/{languageCodeHL}?variant=
            $g->addRoute('GET', '/text/{kind}/{subject}/{languageCodeHL}',
                $call(\App\Controllers\StudyTextController::class, 'webFetch'));

            $g->addRoute('GET', '/seasonal/{site}/{languageCodeGoogle}',
                $call(\App\Controllers\SeasonalTextController::class, 'webFetch'));

            // GET /api/v2/translate/lessonContent/{languageCodeHL}/{study}/{lesson}?jf=
            $g->addRoute('GET', '/lessonContent/{languageCodeHL}/{study}/{lesson}',
                $call(\App\Controllers\BibleStudyJsonController::class, 'webFetchLessonContent'));
        }
    );
 
    // legacy
    $r->addGroup($basePath . '/api/bible', function (RouteCollector $group) use ($container) {
        $group->addRoute('GET', '/best/{languageCodeHL}', function ($params) use ($container) {
            return $container->get(App\Controllers\BibleController::class)
                ->webGetBestBibleByLanguageCodeHL($params['languageCodeHL']);
        });
    });
    $r->addGroup($basePath . '/api/study', function (RouteCollector $group) use ($container) {
        $group->addRoute('GET', '/dbs/languages', function () use ($container) {
            return $container->get(App\Controllers\Language\DbsLanguageReadController::class)
                ->webGetLanguagesWithCompleteBible();
        });
        $group->addRoute('GET', '/dbs/languages/summary', function () use ($container) {
            return $container->get(App\Controllers\Language\DbsLanguageReadController::class)
                ->webGetSummaryOfLanguagesWithCompleteBible();
        });
        $group->addRoute('GET', '/dbsandjvideo/languages/summary', function () use ($container) {
            return $container->get(App\Controllers\Language\DbsLanguageReadController::class)
                ->webGetSummaryOfLanguagesWithCompleteBibleAndJVideo();
        });
        $group->addRoute('GET', '/titles/{study}/{languageCodeHL}', function () use ($container) {
            return $container->get(App\Controllers\BibleStudy\StudyTitleController::class)
                ->webGetTitleForStudy();
        });
        $group->addRoute('GET', '/{study}/{format}/{session}/{language1}[/{language2}]', function ($args) use ($container) {
            // Get the controller instance from the DI container
            $controller = $container->get(App\Controllers\BibleStudyController::class);
            // Call the method with the route arguments
            return $controller->webRequestToFetchStudy($args);
        });
    });
    // translate
    $r->addGroup($basePath . '/api/translate', function (RouteCollector $group) use ($container) {

        $group->addRoute('GET', '/cron/{token}', function ($args) use ($container) {
            $processor = $container->get(\App\Controllers\TranslationQueueController::class);
            $processor->runIfAuthorized($args['token']);
        });
        $group->addRoute('GET', '/interface/{languageCodeHL}/{app}', function ($args) use ($container) {
            $controller = $container->get(App\Controllers\TranslationFetchController::class);
            return $controller->webFetchInterface($args);
        });
        $group->addRoute('GET', '/commonContent/{languageCodeHL}/{study}', function ($args) use ($container) {
            $controller = $container->get(App\Controllers\TranslationFetchController::class);
            return $controller->webFetchCommonContent($args);
        });
        $group->addRoute('GET', '/lessonContent/{languageCodeHL}/{languageCodeJF}/{study}/{lesson}', function ($args) use ($container) {
            $controller = $container->get(App\Controllers\BibleStudyJsonController::class);
            return $controller->webFetchLessonContent($args);
        });

        $group->addRoute('GET', '/lessonContent/{languageCodeHL}/{study}/{lesson}', function ($args) use ($container) {
            $controller = $container->get(App\Controllers\BibleStudyJsonController::class);
            return $controller->webFetchLessonContent($args);
        });

        $group->addRoute('GET', '/lessonContent/ping', function () {
            error_log("🔔 lessonContent/ping route hit!");
            return new \App\Responses\JsonResponse(['ping' => 'pong']);
        });

        $group->addRoute('GET', '/videoUrls/jvideo/{languageCodeJF}', function ($args) use ($container) {
            $controller = $container->get(App\Controllers\Video\JesusVideoUrlController::class);
            return $controller->webFetchJesusVideoUrls($args);
        });
    });
};
