<?php
declare(strict_types=1);

namespace App\Support\Cache;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

final class SimpleArrayCache implements CacheInterface
{
    /** @var array<string, array{value:mixed, expires:int|null}> */
    private array $items = [];

    /** @param string $key */
    private function assertKey(string $key): void
    {
        if ($key === '') {
            throw new class('Empty key') extends \InvalidArgumentException
                implements InvalidArgumentException {};
        }
        if (preg_match('/[{}\(\)\/\\\@\:\s]/', $key)) {
            throw new class('Invalid key: '.$key) extends \InvalidArgumentException
                implements InvalidArgumentException {};
        }
    }

    /** @param null|int|\DateInterval $ttl */
    private function ttlToExpiry(null|int|\DateInterval $ttl): ?int
    {
        if ($ttl === null) return null;
        if ($ttl instanceof \DateInterval) {
            $dt = new \DateTimeImmutable('now');
            return (int) $dt->add($ttl)->format('U');
        }
        if ($ttl <= 0) return 0; // immediately expired
        return time() + $ttl;
    }

    private function isExpired(?int $exp): bool
    {
        return $exp !== null && $exp !== 0 && time() >= $exp;
    }

    public function get($key, $default = null)
    {
        $this->assertKey((string) $key);
        if (!isset($this->items[$key])) return $default;
        [$val, $exp] = [$this->items[$key]['value'], $this->items[$key]['expires']];
        if ($this->isExpired($exp)) {
            unset($this->items[$key]);
            return $default;
        }
        return $val;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->assertKey((string) $key);
        $this->items[$key] = [
            'value'   => $value,
            'expires' => $this->ttlToExpiry($ttl),
        ];
        return true;
    }

    public function delete($key): bool
    {
        $this->assertKey((string) $key);
        unset($this->items[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->items = [];
        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        if (!is_iterable($keys)) {
            throw new class('keys not iterable') extends \InvalidArgumentException
                implements InvalidArgumentException {};
        }
        $result = [];
        foreach ($keys as $k) {
            $result[$k] = $this->get((string) $k, $default);
        }
        return $result;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        if (!is_iterable($values)) {
            throw new class('values not iterable') extends \InvalidArgumentException
                implements InvalidArgumentException {};
        }
        foreach ($values as $k => $v) {
            $this->set((string) $k, $v, $ttl);
        }
        return true;
    }

    public function deleteMultiple($keys): bool
    {
        if (!is_iterable($keys)) {
            throw new class('keys not iterable') extends \InvalidArgumentException
                implements InvalidArgumentException {};
        }
        foreach ($keys as $k) {
            $this->delete((string) $k);
        }
        return true;
    }

    public function has($key): bool
    {
        $this->assertKey((string) $key);
        if (!isset($this->items[$key])) return false;
        $exp = $this->items[$key]['expires'];
        if ($this->isExpired($exp)) {
            unset($this->items[$key]);
            return false;
        }
        return true;
    }
}
