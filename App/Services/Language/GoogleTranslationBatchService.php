<?php

namespace App\Services\Language;

use App\Configuration\Config;
use App\Contracts\Translation\TranslationProvider;
use App\Services\LoggerService;
use Throwable;


/*
Keep it only if you want the worker to do automatic MT 
when reuse fails. If you’re staying “reuse-only + manual” for now, 
you can delete or park it. 
It’s not required for the current system to work.
If you do want automatic MT now

Wire TranslationBatchService into the worker and call it when reuseExistingIfPossible() returns false, 
then upsert into i18n_translations and delete the job.
*/

class GoogleTranslationBatchService implements TranslationProvider
{
    private const MAX_Q_PER_REQ        = 100;   // # of strings per request (v2 supports multiple)
    private const MAX_CHARS_PER_REQ    = 4500;  // keep well under ~5k recommended size
    private const RETRIES              = 3;
    private const CONNECT_TIMEOUT_SECS = 5;
    private const TIMEOUT_SECS         = 20;

    protected string $apiKey;
    

    public function __construct()
    {
        $this->apiKey = (string) Config::get('api.google_translate_apiKey');
        if ($this->apiKey === '') {
            LoggerService::logError('GoogleTranslationBatchService-36', 'Missing Google API key.');
            throw new \RuntimeException('Google API key is required.');
        }
        $this->debugService = Config::get('logging.i18n_debug') ?? false;
    }

        /**
     * Translate a single string via the batch endpoint.
     *
     * @param string $text            English text to translate.
     * @param string $targetLanguage  Target language code (e.g., "gu").
     * @param string $sourceLanguage  Source language code (default "en").
     * @param string $format          "text" | "html" (default "text").
     * @return array{0:bool,1:string,2:int|null,3:string|null,4:int|null}
     *         Tuple: [ok, translated, httpCode, error, respBytes]
     */
    public function translate(
        array $texts,
        string $targetLanguage,
        string $sourceLanguage = 'en',
        string $format = 'text'
    ): array {
      
        LoggerService::logDebugI18n('GoogleTranslateService-56', [
            'texts' => $texts,
            'targetLanguage' => $targetLanguage,
            'sourceLanguage' => $sourceLanguage,
            'format'=> $format
        ]);
 
        // Delegate to the batch method to keep logic in one place.
        [$ok, $list, $code, $err, $len] = $this->translateBatch(
            $texts,
            $targetLanguage,
            $sourceLanguage,
            $format
        );
        $translated = $list[0] ?? '';
        LoggerService::logDebugI18n('GoogleTranslationBatchService-70', $translated);
        return [$ok, $translated, $code, $err, $len];
    }

    /**
     * Sends a batch of texts to Google Translate (v2).
     *
     * @param array<int,string> $texts          English texts to translate.
     * @param string            $targetLanguage Target ISO (e.g. "gu")
     * @param string            $sourceLanguage Source ISO (default "en")
     * @param string            $format         "text" | "html" (default "text")
     * @return array<int,string>                Translated strings in the same order/size.
     */
    public function translateBatch(
        array $texts,
        string $targetLanguage,
        string $sourceLanguage = 'en',
        string $format = 'text'
    ): array {
        LoggerService::logDebugI18n('GoogleTranslationBatchService-90', 
            ['texts' =>$texts,
            'targetLanguage' => $targetLanguage,
            'sourceLanguage'=> $sourceLanguage,
            'format'=>$format] );

        if (empty($texts)) {
            LoggerService::logDebugI18n('GoogleTranslationBatchService-96', 'No Texts' );
           // Always return a 5-tuple: ok, list, httpCode, err, len
            return [true, [], null, null, 0]; // httpCode=null => no request made
        }

        // 1) Deduplicate to save quota, but preserve original order.
        $originalOrder = $texts;
        $unique = [];
        $indexMap = []; // original index -> unique index
        foreach ($originalOrder as $i => $t) {
            $t = (string) $t;
            if (!array_key_exists($t, $unique)) {
                $unique[$t] = count($unique);
            }
            $indexMap[$i] = $unique[$t];
        }
        $uniqueList = array_keys($unique);

        // 2) Chunk by count and approximate chars
        $chunks = [];
        $current = [];
        $charCount = 0;
        foreach ($uniqueList as $s) {
            $len = mb_strlen($s, 'UTF-8');
            if (
                count($current) >= self::MAX_Q_PER_REQ ||
                ($charCount + $len) > self::MAX_CHARS_PER_REQ
            ) {
                if (!empty($current)) {
                    $chunks[] = $current;
                }
                $current = [];
                $charCount = 0;
            }
            $current[] = $s;
            $charCount += $len;
        }
        if (!empty($current)) {
            $chunks[] = $current;
        }

        // 3) Call API per chunk with retries
        $translatedByUniqueIndex = array_fill(0, count($uniqueList), '');

        foreach ($chunks as $chunk) {
            LoggerService::logDebugI18n('GoogleTranslationBatchService-139', 
                ['chunk' =>$chunk,
                'targetLanguage' => $targetLanguage,
                'sourceLanguage'=> $sourceLanguage,
                'format'=>$format] );
        
            $res = $this->callGoogleV2WithRetries($chunk, $targetLanguage, $sourceLanguage, $format);
            // Map back to the unique indices
            foreach ($chunk as $i => $src) {
                $uniIdx = $unique[$src];
                $translatedByUniqueIndex[$uniIdx] = $res[$i] ?? '';
            }
        }

        // 4) Restore original order
        $out = [];
        foreach ($indexMap as $origIdx => $uniIdx) {
            $out[$origIdx] = $translatedByUniqueIndex[$uniIdx] ?? '';
        }
        $list = array_values($out);
        $len  = count($list);
        LoggerService::logDebugI18n('GoogleTranslationBatchService-159', $list);
        // If you have a real HTTP code from upstream, use it instead of 200.
        // Likewise, propagate any $err message you captured earlier.
        $httpCode = 200;
        $err = null;
        return [true, $list, $httpCode, $err, $len];     
    }

    /**
     * Call Google v2 with retries/backoff. Returns translations aligned to $texts.
     *
     * @param array<int,string> $texts
     * @return array<int,string>
     */
    private function callGoogleV2WithRetries(
        array $texts,
        string $targetLanguage,
        string $sourceLanguage,
        string $format
    ): array {
        $attempt = 0;
        $lastBody = null;
        $lastCode = 0;
        $lastErr  = null;

        while ($attempt < self::RETRIES) {
            [$ok, $translations, $httpCode, $err, $bodyLen] =
                $this->callGoogleV2Once($texts, $targetLanguage, $sourceLanguage, $format);

            if ($ok) {
                LoggerService::logDebugI18n('GoogleTranslationBatchService-191', $translations);
                return $translations;
            }

            $attempt++;
            $lastBody = $bodyLen; // we only keep length for logs (avoid PII)
            $lastCode = $httpCode;
            $lastErr  = $err;

            // Backoff on 429/5xx
            if ($httpCode >= 500 || $httpCode === 429) {
                $sleepMs = (int) (pow(2, $attempt - 1) * 500 + random_int(0, 200)); // 0.5s, 1s, 2s + jitter
                usleep($sleepMs * 1000);
                continue;
            }

            // Non-retryable error
            break;
        }
        LoggerService::logDebugI18n(
            'GoogleTranslationBatchService',
            "Google v2 failed after {$attempt} attempts; http={$lastCode}; err=" . ($lastErr ?? 'n/a') . "; respLen=" . ($lastBody ?? 0)
        );

        return array_fill(0, count($texts), '');
    }

    /**
     * Single HTTP call to Google v2 translate endpoint.
     *
     * @param array<int,string> $texts
     * @return array{0: bool, 1: array<int,string>, 2: int, 3: ?string, 4: int} [ok, translations, httpCode, error, bodyLen]
     */
    private function callGoogleV2Once(
        array $texts,
        string $targetLanguage,
        string $sourceLanguage,
        string $format
    ): array {
        $url = 'https://translation.googleapis.com/language/translate/v2?key=' . urlencode($this->apiKey);
        LoggerService::logDebugI18n('GoogleTranslationBatchService-224',$url);
        $payload = [
            'q'      => array_values($texts), // preserves order
            'source' => $sourceLanguage,
            'target' => $targetLanguage,
            'format' => $format,              // "text" or "html"
            'model'  => 'nmt',                // be explicit
        ];
        LoggerService::logDebugI18n('GoogleTranslationBatchService-231',$payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: MyLanguageApp/1.0'
            ],
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECS,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_ENCODING       => '', // allow gzip/deflate
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch) ?: null;
        curl_close($ch);

        // Minimal logging: status + body length; avoid logging text content
        $respLen = is_string($response) ? strlen($response) : 0;
        LoggerService::logDebugI18n('TranslationBatchService-264', "HTTP {$httpCode}; bytes={$respLen}");

        if (!is_string($response) || $httpCode !== 200) {
            return [false, array_fill(0, count($texts), ''), $httpCode, $error, $respLen];
        }

        $data = json_decode($response, true);
        LoggerService::logDebugI18n('TranslationBatchService-271', [$data]);
        $items = $data['data']['translations'] ?? [];

        // Align translations to input order/size
        $out = [];
        foreach ($items as $row) {
            $out[] = (string) ($row['translatedText'] ?? '');
        }
        // If Google returned fewer items, pad to size
        if (count($out) < count($texts)) {
            $out = array_pad($out, count($texts), '');
        }

        return [true, $out, $httpCode, null, $respLen];
    }
}
