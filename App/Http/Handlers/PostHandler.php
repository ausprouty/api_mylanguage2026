<?php

declare(strict_types=1);

namespace App\Http\Handlers;

use App\Middleware\PostAuthorizationMiddleware;
use Psr\Container\ContainerInterface;

/**
 * PostHandler
 *
 * A tiny “HTTP adapter” for FastRoute POST endpoints.
 *
 * Why this exists
 * ---------------
 * In this project, controllers (like PassageRetrieverController) are written to
 * accept a single `$args` array and return an array payload.
 *
 * FastRoute handlers, however, receive route params and typically must deal with
 * request-body parsing, authorization, and JSON output.
 *
 * This class keeps that HTTP plumbing OUT of routes.php and OUT of controllers.
 *
 * Responsibilities
 * ----------------
 * - Authorize the request and read/sanitize the POST body
 *   (delegated to PostAuthorizationMiddleware).
 * - Combine URL route parameters + sanitized POST body into a single `$args`.
 * - Resolve the controller from the DI container.
 * - Invoke the controller and serialize the returned payload as JSON.
 *
 * It intentionally does NOT:
 * - contain business logic (that belongs in controllers/services)
 * - decide which route maps to which controller (that belongs in routes.php)
 *
 * Contract between Handler and Controllers
 * ----------------------------------------
 * The controller will be invoked like:
 *
 *     $controller($args)
 *
 * Where `$args` contains:
 *
 *     $args['route'] : array  Route params from FastRoute (e.g. /users/{id})
 *     $args['body']  : array  Sanitized POST body (JSON or form fields)
 *
 * And the controller is expected to return an array payload, which we return
 * to the caller as JSON:
 *
 *     header('Content-Type: application/json')
 *     echo json_encode($payload)
 *
 * Error Handling
 * --------------
 * PostAuthorizationMiddleware::getDataSet() is expected to throw a RuntimeException
 * (or similar) when authorization fails or the request body is invalid.
 *
 * We catch it here so routes stay clean, and we return a consistent JSON error:
 *
 *     { "error": "..." }
 *
 * Usage (routes.php)
 * ------------------
 * Create one instance after you load the container:
 *
 *     $container   = require __DIR__ . '/../Configuration/container.php';
 *     $postHandler = new \App\Http\Handlers\PostHandler($container);
 *
 * Then attach handlers to routes:
 *
 *     $g->addRoute(
 *         'POST',
 *         '/passage',
 *         $postHandler->make(
 *             \App\Controllers\BiblePassage\PassageRetrieverController::class
 *         )
 *     );
 */
final class PostHandler
{
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * Create a FastRoute-compatible callable for a controller class.
     *
     * @param string $controllerClass Fully-qualified controller class name.
     *                               The controller must be invokable:
     *                               `public function __invoke(array $args): array`
     *
     * @return callable A function (array $routeParams): ?mixed compatible with FastRoute.
     */
    public function make(string $controllerClass): callable
    {
        return function (array $routeParams) use ($controllerClass) {
            // 1) Authorize + read + sanitize POST body (JSON or form).
            //    If this fails, middleware throws and we return JSON error.
            try {
                $dataSet = PostAuthorizationMiddleware::getDataSet();
            } catch (\RuntimeException $e) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(
                    ['error' => $e->getMessage()],
                    JSON_UNESCAPED_UNICODE
                );
                return null;
            }

            // 2) Normalize the args contract expected by controllers.
            $args = [
                'route' => $routeParams,
                'body'  => $dataSet,
            ];

            // 3) Resolve controller from container and invoke.
            $controller = $this->container->get($controllerClass);
            $payload = $controller($args);

            // 4) Serialize controller payload as JSON.
            //    This avoids “mystery output” and keeps all POST endpoints consistent.
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);

            return null;
        };
    }
}
