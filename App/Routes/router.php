<?php
require __DIR__ . '/../../vendor/autoload.php';

use FastRoute\RouteCollector;
use App\Services\LoggerService;

// Load routes (returns a closure that accepts RouteCollector)
$routes = require __DIR__ . '/routes.php';

// Build dispatcher
$dispatcher = FastRoute\simpleDispatcher(function (RouteCollector $r) use ($routes) {
    $routes($r);
});

// Extract method + path
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$path   = parse_url($uri, PHP_URL_PATH) ?: '/';

// (Optional) tiny log so you can see the exact string dispatched
LoggerService::logInfo('router.dispatch', ['method' => $method, 'path' => $path]);

// Dispatch
$routeInfo = $dispatcher->dispatch($method, $path);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        LoggerService::logError('router.not_found', ['path' => $path]);
        http_response_code(404);
        echo '404 Not Found';
        break;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        echo '405 Method Not Allowed';
        break;

    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars    = $routeInfo[2];
        $resp    = is_callable($handler) ? $handler($vars) : null;
        if (is_string($resp)) echo $resp;
        break;
}
