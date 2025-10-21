<?php
namespace App\Http;

use Exception;

class CurlHttpClient implements HttpClientInterface
{
    public function get(string $url, ?RequestOptions $opt = null): HttpResponse
    {
        $opt ??= new RequestOptions();

        $ch = curl_init();
        $hdrs = [];
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $opt->timeout,
            CURLOPT_CONNECTTIMEOUT => $opt->connectTimeout,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => $opt->userAgent ?? 'HL/HttpClient',
            CURLOPT_HTTPHEADER => $this->formatHeaders($opt),
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$hdrs) {
                $len = strlen($line);
                $t = trim($line);
                if ($t === '' || str_starts_with($t, 'HTTP/')) return $len;
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                $hdrs[strtolower($k)] = $v;
                return $len;
            },
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_errno($ch) . ': ' . curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL error ' . $err);
        }
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
        $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: null;
        curl_close($ch);

        $s = (string) $body;
        if ($opt->maxBytes !== null && strlen($s) > $opt->maxBytes) {
            throw new Exception('Response too large');
        }

        return new HttpResponse($code, $s, $ctype, $hdrs, $final);
    }

    private function formatHeaders(RequestOptions $opt): array
    {
        $h = $opt->headers;
        if ($opt->accept) $h['Accept'] = $opt->accept;
        $out = [];
        foreach ($h as $k => $v) $out[] = $k . ': ' . $v;
        return $out;
    }
}
