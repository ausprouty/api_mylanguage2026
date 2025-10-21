<?php
declare(strict_types=1);

namespace App\Services\Language;

use App\Contracts\Translation\ProviderSelector as Contract;
use App\Contracts\Translation\TranslationProvider;

/**
 * Selects a TranslationProvider based on config/env.
 *
 * Policy:
 * - If i18n.autoMt.enabled = false  -> 'null'
 * - If env in ['local','dev']       -> 'google' only if provider === 'google', else 'null'
 * - Else (remote/other)             -> honor provider; default 'google'
 *
 * The $get callable allows deterministic tests (defaults to Config::get).
 */
final class TranslationProviderSelector implements Contract
{
    /** @var array<string, class-string<TranslationProvider>> */
    private array $map;

    /** @var callable(string,mixed):mixed */
    private $get;

    /**
     * @param array<string, class-string<TranslationProvider>> $map
     * @param callable(string, mixed):mixed|null $get  Usually Config::get
     */
    public function __construct(array $map, ?callable $get = null)
    {
        $this->map = $map;
        $this->get = $get ?? static fn(string $k, mixed $d) => \App\Configuration\Config::get($k, $d);
    }

    /** Convenience wrapper for the injected getter */
    private function cfg(string $key, mixed $default = null): mixed
    {
        return ($this->get)($key, $default);
    }

    private function cfgBool(string $key, bool $default = false): bool
    {
        $v = $this->cfg($key, $default);
        if (is_bool($v)) return $v;
        $s = strtolower((string)$v);
        if (in_array($s, ['1','true','yes','on'], true))  return true;
        if (in_array($s, ['0','false','no','off'], true)) return false;
        return $default;
    }

    /**
     * Returns the selected provider key ('google' or 'null').
     */
    public function chosenKey(): string
    {
        // env is set by Config::initialize() as 'env'; fall back to 'environment'
        $env = strtolower((string) $this->cfg('env', $this->cfg('environment', 'remote')));

        // If disabled, always null
        if (!$this->cfgBool('i18n.autoMt.enabled', true)) {
            return 'null';
        }

        // Base default by env
        $defaultByEnv = in_array($env, ['local','dev'], true) ? 'null' : 'google';
        $provider = strtolower((string) $this->cfg('i18n.autoMt.provider', $defaultByEnv));

        if (in_array($env, ['local','dev'], true)) {
            // Dev/local: explicit opt-in to Google; otherwise null for safety
            return $provider === 'google' ? 'google' : 'null';
        }

        // Remote/other: honor configured provider; unknown -> null
        return array_key_exists($provider, $this->map) ? $provider : 'null';
    }

    /**
     * @return class-string<TranslationProvider>
     */
    public function resolveProviderClass(): string
    {
        $key = $this->chosenKey();
        return $this->map[$key] ?? $this->map['null'];
    }

    /**
     * Back-compat for App\Contracts\Translation\ProviderSelector.
     * @return class-string<\App\Contracts\Translation\TranslationProvider>
     */
    public function chosenClass(): string
    {
        return $this->resolveProviderClass();
    }
}
