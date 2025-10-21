<?php
require 'vendor/autoload.php';


use App\Services\LoggerService;
use FastRoute\RouteCollector;


// Include the routes file, which defines all application routes
$routes = require 'routes.php';

// Create the FastRoute dispatcher using the defined routes
$dispatcher = FastRoute\simpleDispatcher($routes);

// Fetch the HTTP method (e.g., GET, POST) and the request URI
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip the query string (?foo=bar) from the URI if it exists
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
// Decode the URI to ensure proper handling of encoded characters
$uri = rawurldecode($uri);

// Dispatch the request to determine the appropriate route or handler
$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        // Route not found - include a 404 page
        include 'App/Views/404.php';
        break;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        // Method not allowed for the route
        $allowedMethods = $routeInfo[1];
        header("HTTP/1.0 405 Method Not Allowed");
        echo '405 Method Not Allowed';
        break;

    case FastRoute\Dispatcher::FOUND:
        // Route found - proceed to handle the request
        $handler = $routeInfo[1]; // The defined handler for this route
        $vars = $routeInfo[2];    // Route parameters (if any)

        if (is_callable($handler)) {
            // If the handler is callable (e.g., a closure or method), call it with parameters
            $response = call_user_func($handler, $vars);

            // Output the response if it is not null
            if ($response !== null) {
                if (is_array($response) || is_object($response)) {
                    // If the response is an array or object, return it as JSON
                    header('Content-Type: application/json');
                    echo json_encode($response);
                } else {
                    // Otherwise, output the response as plain text
                    echo $response;
                }
            }
        } elseif (file_exists($handler)) {
            // Legacy PHP file handling - the file is expected to handle its own output
            include $handler;
        } else {
            // Handler is not found - log the error or display a default message
            echo 'Handler not found';
        }
        break;
}
