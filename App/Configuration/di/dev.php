<?php

use Psr\SimpleCache\CacheInterface;

return [
    // Dev: keep cache as NullCache (quiet, predictable)
    CacheInterface::class => DI\autowire(App\Infra\NullCache::class),
];
