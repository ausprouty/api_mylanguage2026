<?php
declare(strict_types=1);

namespace App\Factories;

use App\Services\Web\YouVersionConnectionService;

final class YouVersionConnectionFactory
{
    /**
     * $endpoint like "111/JHN.3.NIV" (no leading slash). HTML expected.
     */
    public function fromPath(
        string $endpoint,
        bool $autoFetch = true,
        bool $salvageJson = false
    ): YouVersionConnectionService {
        return new YouVersionConnectionService($endpoint, $autoFetch, $salvageJson);
    }
}
