<?php
declare(strict_types=1);

namespace App\Services\Language;

use App\Contracts\Translation\ProviderSelector as Contract;
use App\Contracts\Translation\TranslationProvider;
use App\Services\LoggerService;
use App\Configuration\Config;

/**
 * Selects a TranslationProvider based on config/environment.
 *
 * Policy:
 * If i18n.autoMt (or i18n.autoMt.enabled) is false -> 'null'
 * - If environment in ['local','dev'] -> 'google' only if provider==='google'
 * - Else (remote/other) -> honor provider; default 'google'
 *
 * The $get callable allows deterministic tests (defaults to Config::get).
 */
final class TranslationProviderSelector implements Contract
{
    /** @var array<string, class-string<TranslationProvider>> */
    private array $map;

   

    /**
     * @param array<string, class-string<TranslationProvider>> $map
    */
   public function __construct(array $map)
    {
        $this->map = $map;
    }

    /**
     * Returns the selected provider key ('google' or 'null').
     */
    public function chosenKey(): string
    {
        // Prefer 'environment', fall back to 'env', default 'remote'
        $env = (string) Config::get('environment', Config::get('env', 'remote'));
        $env = strtolower($env);
        LoggerService::logDebugI18n('TPS.env', [
            'env' => $env,
        ]);
        if (!\App\Configuration\Config::getBool('i18n.autoMt.enabled', true)) {
            return 'null';
        }

        // Default provider depends on env
        $defaultByEnv = in_array($env, ['local','dev'], true)
            ? 'null'
            : 'google';
        $provider = (string) Config::get('i18n.autoMt.provider', $defaultByEnv);
        $provider = strtolower($provider);
        LoggerService::logDebugI18n('TPS.provider', [
            'provider' => $provider,
        ]);

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
     * @return class-string<TranslationProvider>
     */
    public function chosenClass(): string
    {
        return $this->resolveProviderClass();
    }
}
