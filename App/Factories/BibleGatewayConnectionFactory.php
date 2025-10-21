<?php
declare(strict_types=1);

namespace App\Factories;

use App\Services\Web\BibleGatewayConnectionService;

final class BibleGatewayConnectionFactory
{
    /**
     * $endpoint like "passage/?search=John+3%3A16"
     * Service handles base URL from config and ensures a leading slash.
     */
    public function fromPath(
        string $endpoint,
        bool $autoFetch = true,
        bool $salvageJson = false
    ): BibleGatewayConnectionService {
        return new BibleGatewayConnectionService(
            $endpoint, 
            $autoFetch, 
            $salvageJson);
    }
}
