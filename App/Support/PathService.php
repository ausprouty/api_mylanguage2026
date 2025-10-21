<?php

namespace App\Support;

final class PathService
{
    /**
     * Join multiple path segments into a single normalized path.
     * Works cross-platform (Windows and Linux).
     */
    public function join(string ...$parts): string
    {
        $parts = array_filter($parts, fn ($p) => $p !== '' && $p !== null);
        $parts = array_map(fn ($p) => trim($p, "\\/"), $parts);

        $path = implode('/', $parts);
        $path = preg_replace('#/+#', '/', $path);

        return DIRECTORY_SEPARATOR === '\\'
            ? str_replace('/', '\\', $path)
            : $path;
    }

    /**
     * Ensure that a target path is located within a given base path.
     * Helps protect against directory traversal.
     */
    public function ensureWithin(string $base, string $target): bool
    {
        $b = realpath($base) ?: '';
        $t = realpath($target) ?: '';

        return $b !== '' && $t !== '' && str_starts_with($t, $b);
    }
}
