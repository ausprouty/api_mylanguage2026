<?php
declare(strict_types=1);

namespace App\Http;

final class RequestOptions
{
    public int $timeout = 20;
    public int $connectTimeout = 10;
    public array $headers = [];
    public ?string $userAgent = 'HL/HttpClient';
    public ?string $accept = null;          // e.g. 'application/json'
    public ?int $maxBytes = 5_000_000;      // response cap
}

final class HttpResponse
{
    public function __construct(
        public readonly int $code,          // HTTP status code
        public readonly string $body,
        public readonly ?string $contentType,
        public readonly array $headers,
        public readonly ?string $finalUrl
    ) {}
}

interface HttpClientInterface
{
    public function get(string $url, ?RequestOptions $opt = null): HttpResponse;
}
