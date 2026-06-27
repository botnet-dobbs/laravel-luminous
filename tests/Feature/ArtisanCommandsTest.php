<?php

namespace Botnetdobbs\Luminous\Tests\Feature;

use Botnetdobbs\Luminous\LuminousServiceProvider;
use Botnetdobbs\Luminous\Support\CacheManager;
use Botnetdobbs\Luminous\Support\YamlExporter;
use Botnetdobbs\Luminous\Tests\Fixtures\Controllers\PaymentController;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Yaml\Yaml;

class ArtisanCommandsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LuminousServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('luminous.enabled', true);
    }

    protected function defineRoutes($router): void
    {
        $router->prefix('v1')->group(function () use ($router) {
            $router->get('/payments', [PaymentController::class, 'index'])->name('payment.index');
            $router->post('/payments', [PaymentController::class, 'store'])->name('payment.store');
            $router->get('/payments/{id}', [PaymentController::class, 'show'])->name('payment.show');
            $router->delete('/payments/{id}', [PaymentController::class, 'cancel'])->name('payment.cancel');
        });
    }

    public function test_generate_outputs_path_and_schema_counts(): void
    {
        $this->artisan('luminous:generate')
            ->expectsOutputToContain('Paths')
            ->expectsOutputToContain('Schemas in components')
            ->assertExitCode(0);
    }

    public function test_generate_force_clears_and_regenerates(): void
    {
        $this->app['config']->set('luminous.cache.enabled', true);
        $this->app['config']->set('luminous.cache.key', 'luminous:m8:force:test');

        $cache = $this->app->make(CacheManager::class);
        $cache->put(['openapi' => 'STALE', 'info' => ['title' => 'old'], 'paths' => [], 'components' => ['schemas' => []]]);

        $this->artisan('luminous:generate', ['--force' => true])
            ->expectsOutputToContain('Cache cleared')
            ->assertExitCode(0);

        $fresh = $cache->get();
        $this->assertNotNull($fresh);
        $this->assertSame('3.1.0', $fresh['openapi']);

        $cache->flush();
    }

    public function test_generate_validate_passes_on_valid_spec(): void
    {
        $this->artisan('luminous:generate', ['--validate' => true])
            ->expectsOutputToContain('validation passed')
            ->assertExitCode(0);
    }

    public function test_export_json_to_stdout(): void
    {
        $this->artisan('luminous:export', ['--format' => 'json'])
            ->expectsOutputToContain('"openapi":"3.1.0"')
            ->assertExitCode(0);
    }

    public function test_export_json_pretty_to_stdout(): void
    {
        $this->artisan('luminous:export', ['--format' => 'json', '--pretty' => true])
            ->expectsOutputToContain('    "openapi": "3.1.0"')
            ->assertExitCode(0);
    }

    public function test_export_json_to_file(): void
    {
        $outputPath = sys_get_temp_dir().'/luminous_test_export_'.uniqid().'.json';

        $this->artisan('luminous:export', ['--format' => 'json', '--output' => $outputPath])
            ->expectsOutputToContain("Spec written to {$outputPath}")
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);
        $decoded = json_decode(file_get_contents($outputPath), true);
        $this->assertSame('3.1.0', $decoded['openapi']);

        @unlink($outputPath);
    }

    public function test_export_yaml_to_stdout(): void
    {
        if (! class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $this->artisan('luminous:export', ['--format' => 'yaml'])
            ->expectsOutputToContain('openapi:')
            ->assertExitCode(0);
    }

    public function test_export_yaml_fails_when_library_missing(): void
    {
        $this->app->instance(YamlExporter::class, new class extends YamlExporter
        {
            public function isAvailable(): bool
            {
                return false;
            }
        });

        $this->artisan('luminous:export', ['--format' => 'yaml'])
            ->expectsOutputToContain('composer require symfony/yaml')
            ->assertExitCode(1);
    }

    public function test_export_no_cache_skips_stale_cache(): void
    {
        $this->app['config']->set('luminous.cache.enabled', true);
        $this->app['config']->set('luminous.cache.key', 'luminous:m8:nocache:test');

        $cache = $this->app->make(CacheManager::class);
        $cache->put(['openapi' => 'STALE_VERSION', 'info' => ['title' => 'old'], 'paths' => [], 'components' => ['schemas' => []]]);

        $this->artisan('luminous:export', ['--format' => 'json', '--no-cache' => true])
            ->expectsOutputToContain('"openapi":"3.1.0"')
            ->assertExitCode(0);

        $cache->flush();
    }

    public function test_export_invalid_format_returns_failure(): void
    {
        $this->artisan('luminous:export', ['--format' => 'xml'])
            ->expectsOutputToContain('Unsupported format: xml')
            ->assertExitCode(1);
    }

    public function test_generate_force_and_validate(): void
    {
        $this->app['config']->set('luminous.cache.enabled', true);
        $this->app['config']->set('luminous.cache.key', 'luminous:m8:forcevalidate:test');

        $cache = $this->app->make(CacheManager::class);
        $cache->put(['openapi' => 'STALE', 'info' => ['title' => 'old'], 'paths' => [], 'components' => ['schemas' => []]]);

        $this->artisan('luminous:generate', ['--force' => true, '--validate' => true])
            ->expectsOutputToContain('Cache cleared')
            ->expectsOutputToContain('validation passed')
            ->assertExitCode(0);

        $fresh = $cache->get();
        $this->assertNotNull($fresh);
        $this->assertSame('3.1.0', $fresh['openapi']);

        $cache->flush();
    }
}
