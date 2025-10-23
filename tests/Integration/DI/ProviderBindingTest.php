<?php
// tests/Integration/Di/ProviderBindingTest.php
declare(strict_types=1);

namespace Tests\Integration\Di;
use App\Configuration\Config;
use App\Contracts\Translation\TranslationProvider;
use App\Services\Language\NullTranslationBatchService;
use DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;



final class ProviderBindingTest extends TestCase
{
    public function test_null_provider_in_local_env(): void
    {
        // Simulate config (adjust if you load config differently)
        putenv('APP_ENV=local');
        if (method_exists(Config::class, 'initialize')) {
            Config::initialize();
        }
        $root = Config::get('base_dir');
        $di_root = $root . 'App/Configuration/di/';

        $builder = new ContainerBuilder();
        // load your app DI files; include 32-translation.php and others as needed
        $builder->addDefinitions($di_root . '32-translation.php');
        $c = $builder->build();

        $provider = $c->get(TranslationProvider::class);
        $this->assertInstanceOf(NullTranslationBatchService::class, $provider);
    }
}
