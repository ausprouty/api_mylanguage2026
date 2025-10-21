<?php
declare(strict_types=1);

namespace App\Services\Language;

use App\Contracts\Translation\TranslationProvider;
use App\Services\LoggerService;

/**
 * Null/dummy translator for smoke tests. It never calls external APIs.
 * - When prefixMode=true, it prefixes each string with "[<tgt>] ".
 * - Otherwise, it returns texts unchanged.
 *
 * NOTE: TranslationBatchService is a class (not an interface), so we extend it.
 */
final class NullTranslationBatchService implements TranslationProvider
{
        public function __construct(private bool $prefixMode = false) {}

    /**
     * Translate an array of texts. In "null" mode we usually just echo
     * input (optionally prefixing for visibility in dev).
     *
     * @param array<int,string> $texts
     * @return array{0:bool,1:array<int,string>,2:int|null,3:string|null,4:int|null}
     */
    public function translate(
        array $texts,
        string $targetLanguage,
        string $sourceLanguage = 'en',
        string $format = 'text'
    ): array {
        $out = [];
        LoggerService::logDebug('NullTranslationBatchService', 'Ran this service for translate');
        foreach ($texts as $t) {
            $s = (string) $t;
            if ($this->prefixMode && $s !== '') {
                $s = '[' . $targetLanguage . '] ' . $s;
            }
            $out[] = $s;
        }
        $respLen = 0;
        foreach ($out as $s) {
            $respLen = \strlen($s);
        }
        return [true, $out, null, null, $respLen];
    }

    /**
     * Convenience for callers that use a batch-named method.
     * Same contract/tuple as translate(...).
     *
     * @param array<int,string> $texts
     * @return array{0:bool,1:array<int,string>,2:int|null,3:string|null,4:int|null}
     */
    public function translateBatch(
        array $texts,
        string $targetLanguage,
        string $sourceLanguage = 'en',
        string $format = 'text'
    ): array {
        LoggerService::logDebug('NullTranslationBatchService', 'Ran this service for translate');
      
        return $this->translate(
            $texts,
            $targetLanguage,
            $sourceLanguage,
            $format = 'text'
        );
    }
 }