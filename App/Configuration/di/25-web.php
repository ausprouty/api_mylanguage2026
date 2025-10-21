<?php
declare(strict_types=1);

use function DI\autowire;

use App\Factories\CloudFrontConnectionFactory;
use App\Factories\BibleBrainConnectionFactory;
use App\Factories\BibleGatewayConnectionFactory;
use App\Factories\BibleWordConnectionFactory;
use App\Factories\YouVersionConnectionFactory;

return [
    // We create *factories* so DI doesn’t try to build connection services
    // that require runtime `$endpoint`/`$url`.
    CloudFrontConnectionFactory::class   => autowire(),
    BibleBrainConnectionFactory::class   => autowire(),
    BibleGatewayConnectionFactory::class => autowire(),
    BibleWordConnectionFactory::class    => autowire(),
    YouVersionConnectionFactory::class   => autowire(),
];
