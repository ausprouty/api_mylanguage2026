<?php

namespace App\Infra;

use Psr\SimpleCache\CacheInterface;
use DateInterval;

/**
 * Lightweight PSR-16 cache backed by APCu, with a safe in-process fallback
 * when APCu is unavailable (e.g., CLI without apc.enable_cli=1).
 */
final class ApcuSimpleCache implements CacheInterface
{
    private string $prefix;
    private bool $useApcu;

    /** @var array<string, array{v:mixed, e:int}> */
    private static array $fallback = [];

    public function __construct(string $prefix = 'app_')
    {
        $this->prefix  = $prefix;
        $this->useApcu = $this->isApcuUsable();
    }

    // ---- PSR-16: single item ops ------------------------------------------

    public function get($key, $default = null)
    {
        $this->assertValidKey($key);
        $k = $this->prefix . $key;

        if ($this->useApcu) {
            $ok = false;
            $val = apcu_fetch($k, $ok);
            return $ok ? $val : $default;
        }

        if (!isset(self::$fallback[$k])) {
            return $default;
        }
        $item = self::$fallback[$k];
        if ($item['e'] !== 0 && $item['e'] < time()) {
            unset(self::$fallback[$k]);
            return $default;
        }
        return $item['v'];
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);
        $k = $this->prefix . $key;
        $seconds = $this->ttlToSeconds($ttl);

        if ($this->useApcu) {
            // APCu treats 0 as "no expiration"
            return apcu_store($k, $value, max(0, $seconds ?? 0));
        }

        $expires = $seconds === null ? 0 : (time() + max(0, $seconds));
        self::$fallback[$k] = ['v' => $value, 'e' => $expires];
        return true;
    }

    public function delete($key): bool
    {
        $this->assertValidKey($key);
        $k = $this->prefix . $key;

        if ($this->useApcu) {
            // Consider non-existence as success per PSR-16 spirit
            $ok = apcu_delete($k);
            return $ok || !apcu_exists($k);
        }

        unset(self::$fallback[$k]);
        return true;
    }

    public function clear(): bool
    {
        if ($this->useApcu && class_exists('APCUIterator')) {
            $it = new \APCUIterator('/^' . preg_quote($this->prefix, '/') . '/');
            foreach ($it as $entry) {
                apcu_delete($entry['key']);
            }
            return true;
        }

        // Fallback: clear only our prefix
        foreach (array_keys(self::$fallback) as $k) {
            if (str_starts_with($k, $this->prefix)) {
                unset(self::$fallback[$k]);
            }
        }
        return true;
    }

    public function has($key): bool
    {
        $this->assertValidKey($key);
        $k = $this->prefix . $key;

        if ($this->useApcu) {
            return apcu_exists($k);
        }

        if (!isset(self::$fallback[$k])) {
            return false;
        }
        $e = self::$fallback[$k]['e'];
        if ($e !== 0 && $e < time()) {
            unset(self::$fallback[$k]);
            return false;
        }
        return true;
    }

    // ---- PSR-16: multiple item ops ----------------------------------------

    public function getMultiple($keys, $default = null): iterable
    {
        $this->assertIterable($keys);

        $result = [];
        foreach ($keys as $key) {
            $this->assertValidKey($key);
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $this->assertIterable($values);

        $allOk = true;
        foreach ($values as $key => $value) {
            $this->assertValidKey($key);
            $ok = $this->set($key, $value, $ttl);
            $allOk = $allOk && $ok;
        }
        return $allOk;
    }

    public function deleteMultiple($keys): bool
    {
        $this->assertIterable($keys);

        $allOk = true;
        foreach ($keys as $key) {
            $this->assertValidKey($key);
            $ok = $this->delete($key);
            $allOk = $allOk && $ok;
        }
        return $allOk;
    }

    // ---- Helpers -----------------------------------------------------------

    private function isApcuUsable(): bool
    {
        if (!function_exists('apcu_fetch')) {
            return false;
        }
        // In CLI, APCu is disabled unless apc.enable_cli=1
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN);
        }
        // Web SAPIs honour apc.enabled (often on by default)
        $enabled = ini_get('apc.enabled');
        return $enabled === '' || $enabled === false ? true : filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    }

    /** @param string|int $ttl */
    private function ttlToSeconds($ttl): ?int
    {
        if ($ttl === null) {
            return null; // no expiration
        }
        if ($ttl instanceof DateInterval) {
            $now = new \DateTimeImmutable();
            return (int) ($now->add($ttl)->format('U') - $now->format('U'));
        }
        if (is_int($ttl)) {
            return $ttl;
        }
        throw new \InvalidArgumentException('TTL must be null, int seconds, or DateInterval');
    }

    /** @param mixed $key */
    private function assertValidKey($key): void
    {
        if (!is_string($key) || $key === '') {
            throw new \InvalidArgumentException('Cache key must be a non-empty string');
        }
        // PSR-16 reserved characters: {}()/\@:
        if (preg_match('/[{}()\[\]\/\\\\@:]/', $key)) {
            throw new \InvalidArgumentException('Cache key contains reserved characters: {}()/\@: []');
        }
    }

    /** @param mixed $iterable */
    private function assertIterable($iterable): void
    {
        if (!is_iterable($iterable)) {
            throw new \InvalidArgumentException('Expected iterable');
        }
    }
}
