<?php
declare(strict_types=1);

namespace App\Factories;
use App\Http\HttpClientInterface;
use App\Services\LoggerService;
use App\Services\Web\BibleBrainConnectionService;

final class BibleBrainConnectionFactory
{

        public function __construct(
        private HttpClientInterface $http,
        private LoggerService $logger
    ) {
        $this->http = $http;
        $this->logger->logDebug('BibleBrainConnectionFactory-16','BBFactory constructed');
    }
    /**
     * Build a connection for an API endpoint, e.g. "bibles" or "text/verse".
     * $params become query string arguments (v, key, format are filled in by the service).
     */
    public function fromPath(
        string $endpoint,
        array $params = [],
        bool $autoFetch = true,
        bool $salvageJson = true
    ): BibleBrainConnectionService {
   
        // BibleBrainConnectionService expects:
        // (string, array, bool, bool)
        return new BibleBrainConnectionService(
            $endpoint,
            $params,
            $autoFetch,
            $salvageJson
        );
    }
}
