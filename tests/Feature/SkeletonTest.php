<?php

namespace Botnetdobbs\Luminous\Tests\Feature;

use Botnetdobbs\Luminous\LuminousServiceProvider;
use Orchestra\Testbench\TestCase;

class SkeletonTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LuminousServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('luminous.enabled', true);
    }

    public function test_json_endpoint_returns_openapi_structure(): void
    {
        $response = $this->get('/docs/openapi.json');

        $response->assertStatus(200);
        $response->assertJsonStructure(['openapi', 'info', 'paths']);
        $this->assertSame('3.1.0', $response->json('openapi'));
    }

    public function test_ui_endpoint_returns_html(): void
    {
        $response = $this->get('/docs');

        $response->assertStatus(200);
        $response->assertSee('swagger-ui', false);
    }

    public function test_config_is_published_from_package(): void
    {
        $this->assertNotNull(config('luminous.path'));
        $this->assertSame('docs', config('luminous.path'));
    }
}
