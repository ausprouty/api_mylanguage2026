<?php
declare(strict_types=1);

use function DI\autowire;
use function DI\get;

use App\Http\HttpClientInterface;
use App\Http\CurlHttpClient;
use App\Http\RetryHttpClient;

/**
 * HTTP stack:
 *   CurlHttpClient       -> low-level transport
 *   RetryHttpClient      -> resilience wrapper
 *   HttpClientInterface  -> resolves to RetryHttpClient
 */
return [

    CurlHttpClient::class => autowire(),

    RetryHttpClient::class => autowire()
        ->constructor(
            get(CurlHttpClient::class),
            3,                     // max retries
            200,                   // backoff (ms)
            [429, 500, 502, 503, 504] // retryable statuses
        ),

    HttpClientInterface::class => get(RetryHttpClient::class),
];
