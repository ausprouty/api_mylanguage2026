<?php

namespace App\Services\Web;

use App\Services\LoggerService;

/**
 * Generic CloudFront fetcher built on WebsiteConnectionService.
 * Works with absolute URLs (signed or public). Assumes JSON by default,
 * enabling preamble-salvage to tolerate stray headers/warnings.
 */
class CloudFrontConnectionService extends WebsiteConnectionService
{
    /**
     * @param string $url        absolute URL to a CloudFront object
     * @param bool   $autoFetch  perform request immediately (default true)
     * @param bool   $salvageJson trim junk before JSON (default true)
     */
    public function __construct(
        string $url,
        bool $autoFetch = true,
        bool $salvageJson = true
    ) {
        // Accept only absolute URLs to avoid surprises
        if (!preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('CloudFront URL must be absolute');
        }

        LoggerService::logInfo('CloudFrontConnectionService-url', $url);

        parent::__construct($url, $autoFetch, $salvageJson);
    }

    /** Convenience: return decoded JSON or null if not JSON / failed. */
    public function getResponse()
    {
        return $this->getJson();
    }
}
