<?php
declare(strict_types=1);

namespace App\Services\BibleStudy;


use App\Contracts\Templates\TemplateAssemblyService;          // ← interface FQCN
use App\Contracts\Translation\TranslationService;
use Psr\SimpleCache\CacheInterface;

final class TextBundleResolver
{
    public function __construct(
        private TemplateAssemblyService $templates,
        private TranslationService $translator,
        private CacheInterface $cache
    ) {}

    /**
     * @return array{data:array<string,mixed>, etag:string}
     */
    public function fetch(
        string $kind,
        string $subject,
        string $languageCodeHL,
        ?string $variant = null
    ): array {
        $variant = $variant ?? 'default';
        $ver = $this->templates->version($kind, $subject);

        $tplKey = $this->tplKey($kind, $subject, $ver);
        $base = $this->cache->get($tplKey);
        

        if (!is_array($base)) {
            $base = $this->templates->get($kind, $subject);
            $this->cache->set($tplKey, $base);
        }

        $isBaseLang  = ($languageCodeHL === $this->translator->baseLanguage());
        $variant = \App\Support\i18n\Normalize::normalizeVariant($variant);
        $hasVariant  = $variant !== 'default';
        $normVariant = $hasVariant ? $variant : 'default';

        // Map HTTP/template tuple → DB resource tuple expected by i18n tables
        // DB: i18n_resources(type, subject, variant)
        // Example rows from your dump:
        //  (1) type='interface', subject='app',  variant='wsu'
        //  (2) type='commonContent', subject='hope', variant='wsu'
        if ($kind === 'interface') {
            $resourceSubject = 'app';           // DB resource subject
            $resourceVariant = $subject;        // site code, e.g. 'wsu'
            $clientCode      = $subject;        // i18n_clients.clientCode, e.g. 'wsu'
        } else { // commonContent etc.
            $resourceSubject = $subject;        // e.g. 'hope'
            $resourceVariant = $normVariant;    // e.g. 'wsu' (or 'default')
            // for studies, client is generally the site code/variant
            $clientCode      = $normVariant !== 'default' ? $normVariant : $subject;
        }

        // Build explicit i18n context for DB lookups (do NOT derive from bundle meta)
        $ctx = [
            // Template/info
            'kind'     => $kind,                  // 'interface' | 'commonContent'
            'langHL'   => $languageCodeHL,        // e.g. 'frn00'
            'isBase'   => $isBaseLang,
            'variant'  => $normVariant,           // requested variant

            // DB resource tuple
            'resourceSubject' => $resourceSubject,
            'resourceVariant' => $resourceVariant,

            // Client identity by code (translator will resolve to clientId)
            'clientCode'      => $clientCode,
        ];

        \App\Support\Trace::info('TextBundleResolver decision', [
            'kind'         => $kind,
            'subject'      => $subject,
            'lang'         => $languageCodeHL,
            'baseLang'     => $this->translator->baseLanguage(),
            'variant'      => $normVariant,
            'isBaseLang'   => $isBaseLang,
            'hasVariant'   => $hasVariant,
            'tplKey'       => $tplKey,
            'trKey'        => $this->trKey($kind,$subject,$languageCodeHL,$normVariant,$ver),
        ]);

        // Base-language fast-path, but seed stringIds so i18n_strings is populated
        if ($isBaseLang && !$hasVariant) {
            try {
                $this->translator->translateBundle(
                    $base,
                    $languageCodeHL,
                    'default',
                    $ctx
                );
            } catch (\Throwable $e) {
                \App\Support\Trace::error('i18n seed failed', ['err' => $e->getMessage()]);
            }
            $out = $base;
            $etag = $this->etag($out, $ver);
            return ['data' => $out, 'etag' => $etag];
        }

        $trKey = $this->trKey(
            $kind,
            $subject,
            $languageCodeHL,
            $normVariant,
            $ver
        );

        $out = $this->cache->get($trKey);
        if (is_array($out)) {
            return ['data' => $out, 'etag' => $this->etag($out, $ver)];
        }

        // IMPORTANT: pass explicit context to the translator.
        $out = $this->translator->translateBundle(
            $base,
            $languageCodeHL,
            $normVariant,
            $ctx
        );

        // Optional: annotate meta (non-authoritative)
        if (isset($out['meta']) && is_array($out['meta'])) {
            $out['meta']['resourceKind']     = $kind;
            $out['meta']['resourceSubject']  = $resourceSubject;
            $out['meta']['resourceVariant']  = $resourceVariant;
            $out['meta']['clientCode']       = $clientCode;
            $out['meta']['langHL']           = $languageCodeHL;
            $out['meta']['variant']          = $normVariant;
        }

        $this->cache->set($trKey, $out);

        return ['data' => $out, 'etag' => $this->etag($out, $ver)];
    }

    private function tplKey(
        string $kind,
        string $subject,
        string $ver
    ): string {
        return "tpl:{$kind}:{$subject}:{$ver}";
    }

    private function trKey(
        string $kind,
        string $subject,
        string $lang,
        ?string $variant,
        string $ver
    ): string {
        $v = $variant ?: 'default';
        return "tr:{$kind}:{$subject}:{$lang}:{$v}:{$ver}";
    }

    private function etag(array $payload, string $ver): string
    {
        $hash = hash(
            'xxh128',
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
        return $ver . '-' . $hash;
    }
}
