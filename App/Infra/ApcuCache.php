<?php
declare(strict_types=1);

namespace App\Infra;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Psr\SimpleCache\CacheInterface;

final class ApcuCache implements CacheInterface
{
    /** Convert TTL to seconds (0 = no expiration). */
    private function ttlSeconds(null|int|DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }
        if ($ttl instanceof DateInterval) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            return (int) $now->add($ttl)->format('U') - (int) $now->format('U');
        }
        return max(0, $ttl);
    }

    /** @inheritDoc */
    public function get(string $key, mixed $default = null): mixed
    {
        $ok  = false;
        $val = apcu_fetch($key, $ok);
        return $ok ? $val : $default;
    }

    /** @inheritDoc */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return (bool) apcu_store($key, $value, $this->ttlSeconds($ttl));
    }

    /** @inheritDoc */
    public function delete(string $key): bool
    {
        return (bool) apcu_delete($key);
    }

    /** @inheritDoc */
    public function clear(): bool
    {
        return (bool) apcu_clear_cache();
    }

    /** @inheritDoc */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $k = [];
        foreach ($keys as $key) {
            $k[] = (string) $key;
        }
        $found = apcu_fetch($k); // returns [key => value] for hits
        $out = [];
        foreach ($k as $key) {
            $out[$key] = array_key_exists($key, $found) ? $found[$key] : $default;
        }
        return $out;
    }

    /** @inheritDoc */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $arr = [];
        foreach ($values as $key => $value) {
            $arr[(string) $key] = $value;
        }
        $ttlSec = $this->ttlSeconds($ttl);
        $res = apcu_store($arr, null, $ttlSec); // true or array of failed keys
        return $res === true || $res === [];
    }

    /** @inheritDoc */
    public function deleteMultiple(iterable $keys): bool
    {
        $k = [];
        foreach ($keys as $key) {
            $k[] = (string) $key;
        }
        $res = apcu_delete($k); // true or array of keys that failed
        return $res === true || $res === [];
    }

    /** @inheritDoc */
    public function has(string $key): bool
    {
        return apcu_exists($key);
    }
}
