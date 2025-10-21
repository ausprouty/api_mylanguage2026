<?php
declare(strict_types=1);

namespace App\Support\i18n;

use App\Configuration\Config;

final class ExcludeKeyMatcher
{
    /** @var array<string, true> */
    private array $exact = [];

    /** @var array<string, true> // "prefix." means subtree */
    private array $prefix = [];

    /**
     * @param array<int,string> $bundleExcludes dot-keys like "video" or "meta"
     */
    public function __construct(array $bundleExcludes)
    {
        $defaults = (array)Config::get('i18n.exclude_keys_default', []);
        $all = array_values(array_unique(array_merge($defaults, $bundleExcludes)));

        foreach ($all as $key) {
            $k = trim((string)$key);
            if ($k === '') continue;

            // treat bare key as both the node and its subtree
            $this->exact[$k] = true;
            $this->prefix[$k . '.'] = true;
        }
    }

    /**
     * @param string $dotKey e.g. "video.prefix", "study.title", "meta.version"
     */
    public function isExcluded(string $dotKey): bool
    {
        if (isset($this->exact[$dotKey])) return true;

        foreach ($this->prefix as $pfx => $_) {
            if (str_starts_with($dotKey, $pfx)) return true;
        }
        return false;
    }
}
