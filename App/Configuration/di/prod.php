<?php

use Psr\SimpleCache\CacheInterface;
use App\Infra\ApcuCache;
use App\Infra\NullCache;

return [
    // Prod: upgrade to APCu cache
    CacheInterface::class => function (): CacheInterface {
        return function_exists('apcu_fetch') ? new ApcuCache() : new NullCache();
    },
];
