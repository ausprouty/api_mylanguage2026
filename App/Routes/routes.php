<?php

use FastRoute\RouteCollector;
use App\Configuration\Config;
use App\Responses\JsonResponse;
use App\Services\LoggerService;

return function (RouteCollector $r) {
    // Normalize basePath to: ""  or "/something" (no trailing slash)
    $rawBase = (string) (Config::get('base_path') ?? '');
    $basePath = rtrim('/' . ltrim($rawBase, '/'), '/');
    if ($basePath === '/') { $basePath = ''; } // treat "/" as empty
    LoggerService::logInfo('router.basePath', ['raw' => $rawBase, 'normalized' => $basePath]);
   
    $container = require __DIR__ . '/../Configuration/container.php';

    // Minimal debug endpoint: GET {basePath}/_debug/ping
    $r->addRoute('GET', '/api_mylanguage2026/_debug/ping2', function () {
        header('Content-Type: text/plain');
        echo 'OK literal';
        return null;
    });

    //test
    $r->addGroup($basePath . 'api/test', function (RouteCollector $group) use ($container) {
        $group->addRoute('GET', '', function () use ($container) {
            return $container->get(App\Controllers\TestBibleBrainController::class)
                ->logFiveLanguages();
        });
    });

    // version 2

    $r->addGroup($basePath . '/api/v2/translate', function (RouteCollector $g)
    use ($container) {

        // Unified for interface + commonContent
        // GET /api/v2/translate/text/{kind}/{subject}/{languageCodeHL}?variant=
        $g->addRoute('GET',
            '/text/{kind}/{subject}/{languageCodeHL}',
            function ($args) use ($container) {
                $c = $container->get(App\Controllers\StudyTextController::class);
                return $c->webFetch($args);
            }
        );

        // Lesson content (clean signature). Make JF optional via query ?jf=
        // GET /api/v2/translate/lessonContent/{languageCodeHL}/{study}/{lesson}?jf=
        $g->addRoute('GET',
            '/lessonContent/{languageCodeHL}/{study}/{lesson}',
            function ($args) use ($container) {
                // Controller reads optional $_GET['jf'] if present
                $c = $container->get(App\Controllers\BibleStudyJsonController::class);
                return $c->webFetchLessonContent($args);
            }
        );
    });

    // Lightweight router debug endpoint:
    // GET {basePath}/_debug/router
    $r->addGroup($basePath, function (RouteCollector $g) use ($basePath) {
        $g->addRoute('GET', '/_debug/router', function () use ($basePath) {
            return new JsonResponse([
                'basePath' => $basePath,
                'expectedExample' => $basePath . '/api/v2/translate/text/common/hope/eng00'
            ]);
        });
     });
 };

    Now your tra


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
