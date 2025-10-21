<?php
declare(strict_types=1);

namespace App\Http;

interface HttpClientInterface
{
    public function get(string $url, ?RequestOptions $opt = null): HttpResponse;
}
