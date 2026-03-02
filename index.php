<?php
// index.php

$mode = 'normal';

// ========= Error reporting =========
$isLocal = PHP_SAPI === 'cli'
    ? true
    : (($_SERVER['SERVER_NAME'] ?? '') === 'localhost');

error_reporting($isLocal ? E_ALL : 0);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('log_errors', '1');

// ========= Absolutely no output before headers =====

// ========= Autoload (Composer) =========
require_once __DIR__ . '/vendor/autoload.php';

use App\Support\Trace;
use App\Support\ErrorHandler;
use App\Middleware\CORSMiddleware;

// ========= Trace ID =========
Trace::init();
header('X-Trace-Id: ' . Trace::id(), true);
ErrorHandler::register();

// ========= Middleware chain =========
$middlewares = [
    new CORSMiddleware(),
];

// Run the middleware chain and, at the end, dispatch routes.
applyMiddleware($middlewares, $_SERVER, function () use ($mode) {
    // If you later re-enable mode switching, do it here.
    require_once __DIR__ . '/App/Routes/router.php';
});

function applyMiddleware(array $middlewares, $request, callable $final)
{
    $next = function ($req) use (&$middlewares, &$next, $final) {
        if (empty($middlewares)) {
            return $final($req);
        }
        $mw = array_shift($middlewares);
        return $mw->handle($req, $next);
    };

    return $next($request);
}
