<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Language;

use PHPUnit\Framework\TestCase;
use App\Services\Language\TranslationProviderSelector;
use App\Contracts\Translation\TranslationProvider;

/**
 * Unit tests for ProviderSelector policy:
 * - Defaults: local -> null, remote -> google
 * - Explicit override via i18n.autoMt.provider
 * - Unknown key -> null fallback
 */
final class TranslationProviderSelectorTest extends TestCase
{
    /** @var array<string,class-string<TranslationProvider>> */
    private array $map;

    protected function setUp(): void
    {
        parent::setUp();
        // Use tiny fakes so we don't depend on real providers.
        $this->map = [
            'null'   => FakeNullProvider::class,
            'google' => FakeGoogleProvider::class,
        ];
    }

    /**
     * @dataProvider cases
     * @param array<string,string> $cfg
     */
    public function test_policy(array $cfg, string $expectedKey, string $expectedClass): void
    {
        // Config getter closure used only by the selector in tests.
        $get = static function (string $key, mixed $default) use ($cfg) {
            return $cfg[$key] ?? $default;
        };

        $selector = new TranslationProviderSelector($this->map, $get);

        $this->assertSame($expectedKey, $selector->chosenKey(), 'chosenKey mismatch');
        $this->assertSame($expectedClass, $selector->chosenClass(), 'chosenClass mismatch');
    }

    /** @return array<string,array{0:array<string,string>,1:string,2:string}> */
    public static function cases(): array
    {
        return [
            // No explicit provider; environment drives default
            'default_local_env_uses_null' => [
                ['environment' => 'local'],
                'null',
                FakeNullProvider::class,
            ],
            'default_remote_env_uses_google' => [
                ['environment' => 'remote'],
                'google',
                FakeGoogleProvider::class,
            ],

            // Explicit selection
            'explicit_google' => [
                ['environment' => 'local', 'i18n.autoMt.provider' => 'google'],
                'google',
                FakeGoogleProvider::class,
            ],
            'explicit_null' => [
                ['environment' => 'remote', 'i18n.autoMt.provider' => 'null'],
                'null',
                FakeNullProvider::class,
            ],

            // Unknown key -> null fallback
            'unknown_key_falls_back_to_null' => [
                ['environment' => 'remote', 'i18n.autoMt.provider' => 'deepl'],
                'null',
                FakeNullProvider::class,
            ],
        ];
    }
}

/**
 * Minimal fakes for provider classes.
 * They only satisfy the interface to keep the test self-contained.
 */
final class FakeNullProvider implements TranslationProvider {
    public function translate(array $texts, string $targetLanguage, string $sourceLanguage='en', string $format='text'): array {
        return array_fill(0, count($texts), '');
    }
}
final class FakeGoogleProvider implements TranslationProvider {
    public function translate(array $texts, string $targetLanguage, string $sourceLanguage='en', string $format='text'): array {
        return array_map(static fn($s) => "[{$targetLanguage}] {$s}", $texts);
    }
}
