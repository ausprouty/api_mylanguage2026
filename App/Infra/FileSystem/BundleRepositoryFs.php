<?php

namespace App\Infra\Filesystem;

use App\Contracts\Translation\BundleRepository;
use App\Support\PathService;
use App\Configuration\Config;

final class BundleRepositoryFs implements BundleRepository
{
    public function __construct(private PathService $paths) {}

    /**
     * Returns the merged "master" bundle for the given type.
     * $type: 'interface' | 'commonContent'
     * $sourceKey: site (for interface) or content key (for commonContent)
     * $variant: e.g. 'eng00'
     */
    public function getMaster(
        string $type,
        string $sourceKey,
        string $lang = 'eng00',   // kept for signature compat; not used directly
        ?string $variant = null
    ): array {
        $templatesRoot = Config::getDir('resources.templates'); // .../Resources/templates
        $type = trim($type);

        if ($type !== 'interface' && $type !== 'commonContent') {
            throw new \RuntimeException("Unsupported type '$type' in BundleRepository.");
        }

        $candidates = $this->candidatePaths($templatesRoot, $type, $sourceKey, $variant);

        $foundAny = false;
        $data = [];

        foreach ($candidates as $path) {
            if (!$path || !is_file($path)) {
                continue;
            }
            $foundAny = true;
            $overlay = $this->readJson($path);
            $data = $this->mergeOverlay($data, $overlay);
        }

        if (!$foundAny) {
            $debug = implode(' ; ', array_map(fn($p) => $p ?: '(none)', $candidates));
            error_log("[BundleRepositoryFs] {$type} empty; tried: {$debug}");
            return [];
        }

        // ensure meta exists and carries schema/kind/subject/variant
        if (!isset($data['meta']) || !is_array($data['meta'])) {
            $data['meta'] = [];
        }
        $data['meta']['schema']  = $this->schemaTag($type);
        $data['meta']['kind']    = $data['meta']['kind']    ?? $type;
        $data['meta']['subject'] = $data['meta']['subject'] ?? $sourceKey;
        $data['meta']['variant'] = $data['meta']['variant'] ?? $variant;

        return $data;
    }

    /**
     * Build ordered candidate paths for both kinds, using text.json everywhere.
     *
     * Layout:
     *   Root: {templatesRoot}/app/{kind}
     *
     *   commonContent:
     *     1) {root}/text.json
     *     2) {root}/{subject}/text.json
     *     3) {root}/{subject}/{variant}/text.json          (if $variant)
     *
     *   interface (baseline prefers site-specific, else default; variant overlay prefers site, else default):
     *     1) {root}/{subject}/text.json     OR  {root}/default/text.json
     *     2) {root}/{subject}/{variant}/text.json   OR  {root}/default/{variant}/text.json
     */
    private function candidatePaths(string $templatesRoot, string $kind, string $subject, ?string $variant): array
    {
        $base = $this->paths->join($templatesRoot, 'app', $kind);
        $file = 'text.json';

        if ($kind === 'commonContent') {
            $paths = [
                $this->paths->join($base, $file),
                $this->paths->join($base, $subject, $file),
            ];
            if ($variant) {
                $paths[] = $this->paths->join($base, $subject, $variant, $file);
            }
            return $paths;
        }

        // interface
        $siteBaseline    = $this->paths->join($base, $subject, $file);
        $defaultBaseline = $this->paths->join($base, 'default', $file);

        $paths = [
            is_file($siteBaseline) ? $siteBaseline : $defaultBaseline
        ];

        if ($variant) {
            $siteVariant    = $this->paths->join($base, $subject, $variant, $file);
            $defaultVariant = $this->paths->join($base, 'default', $variant, $file);
            $paths[] = is_file($siteVariant) ? $siteVariant : $defaultVariant;
        }

        return $paths;
    }

    // ---------- helpers ----------

    /**
     * Recursive merge where:
     * - scalar or array overlay values REPLACE base values
     * - NULL overlay values DELETE the base key
     * - "" (empty string) overlay values ALSO DELETE the base key
     * - new keys are added
     */
    private function mergeOverlay(array $base, array $overlay): array
    {
        foreach ($overlay as $k => $v) {
            // deletions
            if ($v === null || $v === '') {
                unset($base[$k]);
                continue;
            }

            // nested arrays: merge into existing array, else replace
            if (is_array($v)) {
                $base[$k] = (isset($base[$k]) && is_array($base[$k]))
                    ? $this->mergeOverlay($base[$k], $v)
                    : $v;
                continue;
            }

            // scalar: replace/add
            $base[$k] = $v;
        }
        return $base;
    }

    /** Strict JSON reader with useful errors. */
    private function readJson(string $path): array
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed reading $path");
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $head = substr($json, 0, 180);
            throw new \RuntimeException("Invalid JSON at $path (head: $head)");
        }
        return $data;
    }

    private function schemaTag(string $kind): string
    {
        return match ($kind) {
            'commonContent' => 'cc/1',
            'interface'     => 'iface/1',
            default         => 'unknown/0',
        };
    }
}
