<?php

use FastRoute\RouteCollector;
use App\Configuration\Config;
use App\Responses\JsonResponse;
use App\Services\LoggerService;
use App\Middleware\PostAuthorizationMiddleware;
use Psr\Container\ContainerInterface;

/**
 * Wrap a controller class in a POST-aware handler.
 *
 * - Reads + authorizes + sanitizes body via PostAuthorizationMiddleware.
 * - Receives route parameters from FastRoute.
 * - Passes a single $args array to the controller:
 *     $args['route'] = route params from URL
 *     $args['body']  = sanitized POST body
 */
function postHandler(string $controllerClass, ContainerInterface $container): callable
{
    return function (array $routeParams) use ($controllerClass, $container) {
        // 1) Authorize + get body (JSON or form)
        $dataSet = PostAuthorizationMiddleware::getDataSet();

        if (!is_array($dataSet)) {
            // Authorization failed or invalid body.
            // PostAuthorizationMiddleware already set status + headers.
            echo $dataSet;
            return null;
        }

        // 2) Build the args structure we pass to the controller
        $args = [
            'route' => $routeParams, // ← THIS is where route params go
            'body'  => $dataSet,     // ← Sanitized POST data
        ];

        // 3) Resolve controller from container and invoke
        $controller = $container->get($controllerClass);

        return $controller($args);
    };
}



/**
 * @param RouteCollector $r
 * @param array|string   $postData  Sanitized POST data from
 *                                  PostAuthorizationMiddleware.
 */


return function (RouteCollector $r) {

    // Normalize basePath to: ""  or "/something" (no trailing slash)
    $rawBase = (string) (Config::get('base_path') ?? '');
    $basePath = rtrim('/' . ltrim($rawBase, '/'), '/');
    if ($basePath === '/') { $basePath = ''; } // treat "/" as empty
    LoggerService::logInfo('router.basePath', ['raw' => $rawBase, 'normalized' => $basePath]);
   
    $container = require __DIR__ . '/../Configuration/container.php';

    // root
    $r->addRoute('GET', '/', static function (): void {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode([
            'ok'      => true,
            'service' => 'api2.mylanguage.net.au',
            'time'    => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::RFC3339),
        ]);
    });

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

    // version 2  Available

     $r->addGroup( $basePath . '/api/v2/available',
        function (RouteCollector $g) use ($container) {
            $g->addRoute('POST', '/bibles', postHandler(
                \App\Controllers\Bibles\BiblesAvailableController::class,
                $container
            ));

            $g->addRoute('POST', '/languages', postHandler(
                \App\Controllers\Language\LanguagesAvailableController::class,
                $container
            ));

        }
    );
    // version 2  Bible

    $r->addGroup( $basePath . '/api/v2/bible',
      function (RouteCollector $g) use ($container) {
           $g->addRoute('POST', '/bible/passage', postHandler(
                \App\Controllers\BiblePassage\PassageRetrieverController::class,
                $container
            ));
      }

    );

    // version 2 Translate

    $r->addGroup($basePath . '/api/v2/translate', function (RouteCollector $g)
    use ($container) {

        $g->addRoute('GET', '/cron/{token}', function ($args) use ($container) {
            $processor = $container->get(\App\Controllers\TranslationQueueController::class);
             $c = $container->get(App\Controllers\TranslationQueueController::class);
             return $c->__invoke($args);
        });

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

    
