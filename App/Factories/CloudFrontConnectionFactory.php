<?php
declare(strict_types=1);

namespace App\Factories;

use App\Configuration\Config;
use App\Services\Web\CloudFrontConnectionService;

final class CloudFrontConnectionFactory
{
    /** Build from a relative path under your distribution */
    public function fromPath(
        string $path,
        bool $autoFetch = true,
        bool $salvageJson = true
    ): CloudFrontConnectionService {
        $base = rtrim((string) Config::get('endpoints.cloudfront', 'https://d0000000000000.cloudfront.net'), '/');
        $url  = $base . '/' . ltrim($path, '/');
        return new CloudFrontConnectionService($url, $autoFetch, $salvageJson);
    }

    /** Build directly from an absolute URL */
    public function fromUrl(
        string $url,
        bool $autoFetch = true,
        bool $salvageJson = true
    ): CloudFrontConnectionService {
        return new CloudFrontConnectionService($url, $autoFetch, $salvageJson);
    }
}
