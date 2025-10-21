<?php
declare(strict_types=1);

namespace App\Http;

final class HttpResponse
{
    public function __construct(
        public int $status,
        public array $headers,
        public string $body
    ) {}
}
