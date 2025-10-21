<?php
declare(strict_types=1);

use function DI\autowire;
use function DI\get;

use App\Middleware\CORSMiddleware;
use App\Middleware\PostAuthorizationMiddleware;
use App\Middleware\PreflightMiddleware;

return [

    CORSMiddleware::class            => autowire(),
    PostAuthorizationMiddleware::class => autowire(),
    PreflightMiddleware::class      => autowire(),

    
];
