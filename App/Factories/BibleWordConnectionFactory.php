<?php
declare(strict_types=1);

namespace App\Factories;

use App\Services\Web\BibleWordConnectionService;

final class BibleWordConnectionFactory
{
    /**
     * $endpoint like "en/42/7.htm" (no leading slash). HTML expected.
     */
    public function fromPath(
        string $endpoint,
        bool $salvageJson = false
    ): BibleWordConnectionService {
        return new BibleWordConnectionService($endpoint, $salvageJson);
    }
}
