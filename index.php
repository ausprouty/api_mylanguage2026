<?php
// index.php

// ========= Mode (tests / scripts / normal) =========
$mode = 'normal';

// ========= Error reporting =========
// Use CLI-safe detection and avoid notices on missing SERVER_NAME.
$isLocal = PHP_SAPI === 'cli'
    ? true
    : (($_SERVER['SERVER_NAME'] ?? '') === 'localhost');

error_reporting($isLocal ? E_ALL : 0);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('log_errors', '1');

// ========= Absolutely no output before headers =========
// If Debugging.php *prints* anything, it will break headers.
// Keep it silent-only. Otherwise include it conditionally.
require_once __DIR__ . '/App/Services/Debugging.php';

// ========= Autoload (Composer) =========
require_once __DIR__ . '/vendor/autoload.php';

// ========= Imports (safe to use after autoload) =========
use App\Support\Trace;
use App\Support\ErrorHandler;
use App\Middleware\CORSMiddleware;
use App\Middleware\PreflightMiddleware;
use App\Middleware\PostAuthorizationMiddleware;

// ========= Trace ID (before any response work) =========
Trace::init();
header('X-Trace-Id: ' . Trace::id());
ErrorHandler::register();   // ← now all errors become JSON with traceId

// ========= CORS / Preflight early exit =========
// Handle OPTIONS quickly to avoid running the rest of the stack.
$preflight = new PreflightMiddleware();
$cors      = new CORSMiddleware();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    // Apply CORS headers for preflight and exit.
    $cors->handle($_SERVER, function () { return; });
    $preflight->handle($_SERVER, function () { return; });
    http_response_code(204);
    // No body for preflight
    exit;
}

// ========= Middleware chain =========
$middlewares = [
    $cors,
    $preflight,
];

applyMiddleware($middlewares, $_SERVER);

function applyMiddleware(array $middlewares, $request)
{
    $next = function ($req) use (&$middlewares, &$next) {
        if (empty($middlewares)) {
            return null; // end of chain
        }
        $mw = array_shift($middlewares);
        return $mw->handle($req, $next);
    };
    return $next($request);
}

// ========= Post-authorization (if your app expects it) =========
$postData = PostAuthorizationMiddleware::getDataSet();

// ========= Route dispatching =========
switch ($mode) {
    case 'tests':
        require_once __DIR__ . '/App/Routes/TestLoader.php';
        break;
    case 'scripts':
        require_once __DIR__ . '/App/Routes/ImportLoader.php';
        break;
    default:
        // fall through to router
        break;
}

require_once __DIR__ . '/App/Routes/router.php';
