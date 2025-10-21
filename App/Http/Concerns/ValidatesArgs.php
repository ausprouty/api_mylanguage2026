<?php
declare(strict_types=1);

namespace App\Http\Concerns;

trait ValidatesArgs
{
    /**
     * Returns null if missing, blank after trim, or literal "undefined".
     * Optionally runs a $filter (e.g., normaliser) on the value.
     */
    protected function arg(array $src, string $key, ?callable $filter = null): ?string
    {
        if (!array_key_exists($key, $src)) return null;
        $v = trim((string) $src[$key]);
        if ($v === '' || strcasecmp($v, 'undefined') === 0) return null;
        return $filter ? ($filter)($v) : $v;
    }

    /** Normalise an "id" token (lowercase a–z, 0–9, hyphen). */
    protected function normId(string $v): string
    {
        $t = strtolower(trim($v));
        return preg_replace('/[^a-z0-9-]/', '', $t) ?? '';
    }

    /** Coerce to int or null. */
    protected function argInt(array $src, string $key): ?int
    {
        $s = $this->arg($src, $key);
        if ($s === null) return null;
        return filter_var($s, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }

    /** Coerce to bool or null (accepts true/false/1/0/yes/no). */
    protected function argBool(array $src, string $key): ?bool
    {
        $s = $this->arg($src, $key);
        if ($s === null) return null;
        $map = ['true'=>true,'1'=>true,'yes'=>true,'on'=>true,'false'=>false,'0'=>false,'no'=>false,'off'=>false];
        $k = strtolower($s);
        return $map[$k] ?? null;
    }

    /** Required keys that are missing/invalid (after sanitize). */
    protected function missingRequired(array $src, array $required): array
    {
        $bad = [];
        foreach ($required as $k) {
            if ($this->arg($src, $k) === null) $bad[] = $k;
        }
        return $bad;
    }

    /** Pretty “expected keys” string. */
    protected function expectedKeysMsg(array $req, array $opt): string
    {
        $reqStr = implode(', ', $req);
        $optStr = $opt ? implode(', ', $opt) : '(none)';
        return "Expected keys - required: [$reqStr], optional: [$optStr]";
    }
}
