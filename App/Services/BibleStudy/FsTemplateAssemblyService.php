<?php
declare(strict_types=1);

namespace App\Services\BibleStudy;

use App\Contracts\Templates\TemplateAssemblyService;
use App\Contracts\Templates\TemplatesRootProvider;
use App\Services\LoggerService;
use RuntimeException;

final class FsTemplateAssemblyService implements TemplateAssemblyService
{
    public function __construct(private TemplatesRootProvider $roots) {}

    /** Contract entry point: honors $variant. */
    public function assemble(string $kind, string $subject, ?string $variant = null): array
    {
        return $this->get($kind, $subject, $variant);
    }

    /**
     * Load, overlay (root → key → variant), inject schema.
     * Overlay is **destructive**: new file replaces keys entirely; null/"" deletes.
     */
    public function get(string $kind, string $subject, ?string $variant = null): array
    {
        $root = $this->templatesRoot();

        $candidates = $this->candidatePaths($root, $kind, $subject, $variant);
        $foundAny = false;
        $data = [];

        foreach ($candidates as $path) {
            //LoggerService::logInfo('FsTemplateAssemblyService-candidate', ['path' => $path]);
            if (!is_string($path) || $path === '' || !is_file($path)) {
                continue;
            }
            $foundAny = true;
            $overlay = $this->readJson($path);
            $data = $this->overlayDestructive($data, $overlay); // overlay wins; null/"" deletes
        }
        //LoggerService::logInfo('FsTemplateAssemblyService-data', ['data' => $data]);

        if (!$foundAny) {
            throw new RuntimeException("Template not found for {$kind}/{$subject}" . ($variant ? " (variant={$variant})" : ''));
        }

        // Inject schema & meta hints
        $schema = $this->schemaTag($kind);
        if (!isset($data['meta']) || !is_array($data['meta'])) {
            $data['meta'] = [];
        }
        $data['meta']['schema']  = $schema;
        $data['meta']['kind']    = $data['meta']['kind']    ?? $kind;
        $data['meta']['subject'] = $data['meta']['subject'] ?? $subject;
        $data['meta']['variant'] = $data['meta']['variant'] ?? $variant;

        return $data;
    }

    /** Content hash changes when any contributing file changes (or when variant changes). */
    public function version(string $kind, string $subject, ?string $variant = null): string
    {
        $root = $this->templatesRoot();
        $schema = $this->schemaTag($kind);

        $parts = [];
        foreach ($this->candidatePaths($root, $kind, $subject, $variant) as $p) {
            if ($p && is_file($p)) {
                $parts[] = $p . '|' . (string)@filesize($p) . '|' . (string)@filemtime($p);
            }
        }
        if (!$parts) {
            return $schema . ':0';
        }

        $algo = \in_array('xxh128', \hash_algos(), true) ? 'xxh128' : 'sha1';
        $hash = \hash($algo, implode("\n", $parts)) ?: '0';
        return $schema . ':' . $hash;
    }

    // ---------- path resolution ----------

    /**
     * Build ordered candidate paths for root → key → variant.
     * Works the same for both kinds, and always uses text.json.
     *
     * Layout:
     *   {templatesRoot}/app/{kind}/text.json
     *   {templatesRoot}/app/{kind}/{subject}/text.json
     *   {templatesRoot}/app/{kind}/{subject}/{variant}/text.json   (if $variant)
     */
    private function candidatePaths(string $root, string $kind, string $subject, ?string $variant): array
    {
        $root = rtrim($root, "\\/");
        $baseDir = $root . '/app/' . $kind;
        $file = 'text.json';

        $paths = [
            $baseDir . '/' . $file,
            $baseDir . '/' . $subject . '/' . $file,
        ];
        if ($variant) {
            $paths[] = $baseDir . '/' . $subject . '/' . $variant . '/' . $file;
        }
        return $paths;
    }

    /** Prefer path(), fall back to getTemplatesRoot() for compatibility. */
    private function templatesRoot(): string
    {
        if (method_exists($this->roots, 'path')) {
            $root = $this->roots->path();
        } elseif (method_exists($this->roots, 'getTemplatesRoot')) {
            /** @phpstan-ignore-next-line */
            $root = $this->roots->getTemplatesRoot();
        } else {
            throw new RuntimeException('TemplatesRootProvider has no path() or getTemplatesRoot()');
        }

        if (!is_string($root) || $root === '' || !is_dir($root)) {
            throw new RuntimeException('Templates root provider returned an invalid path: ' . var_export($root, true));
        }
        return $root;
    }

    // ---------- merging & JSON ----------

    /**
     * Destructive overlay:
     * - NULL or "" => DELETE the key from base
     * - arrays/objects/scalars => REPLACE base[key] entirely (no deep merge)
     * - new keys are added
     */
    private function overlayDestructive(array $base, array $overlay): array
    {
        foreach ($overlay as $k => $v) {
            if ($v === null || $v === '') {
                unset($base[$k]);
                continue;
            }
            // replace/add (no recursion)
            $base[$k] = $v;
        }
        return $base;
    }

    /** Strict JSON reader with helpful errors. */
    private function readJson(string $path): array
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Failed reading $path");
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Invalid JSON at $path: " . $e->getMessage(), 0, $e);
        }
        if (!is_array($data)) {
            throw new RuntimeException("JSON at $path did not decode to an object");
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
