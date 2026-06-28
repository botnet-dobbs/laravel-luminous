<?php

namespace Botnetdobbs\Luminous\Tests\Feature;

use Botnetdobbs\Luminous\Extractors\ControllerExtractor;
use Botnetdobbs\Luminous\Extractors\EnumExtractor;
use Botnetdobbs\Luminous\Extractors\RequestExtractor;
use Botnetdobbs\Luminous\Extractors\ResourceExtractor;
use Botnetdobbs\Luminous\Extractors\RouteExtractor;
use Botnetdobbs\Luminous\Generator\ComponentsRegistry;
use Botnetdobbs\Luminous\Generator\OpenApiGenerator;
use Botnetdobbs\Luminous\LuminousServiceProvider;
use Botnetdobbs\Luminous\Support\TypeMapper;
use Botnetdobbs\Luminous\Tests\Fixtures\Controllers\PaymentController;
use Orchestra\Testbench\TestCase;

class WrapResponsesTest extends TestCase
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

    private function makeGenerator(): OpenApiGenerator
    {
        $config = config('luminous');
        $registry = new ComponentsRegistry;
        $enumEx = new EnumExtractor;
        $typeMapper = new TypeMapper($enumEx);
        $requestEx = new RequestExtractor($typeMapper, $registry, $enumEx);
        $resourceEx = new ResourceExtractor($typeMapper, $registry, $enumEx);
        $controllerEx = new ControllerExtractor($requestEx, $resourceEx, $config);
        $routeEx = new RouteExtractor($config);

        return new OpenApiGenerator(
            config: $config,
            routeExtractor: $routeEx,
            controllerExtractor: $controllerEx,
            registry: $registry,
        );
    }

    public function test_without_wrap_responses_schema_is_resource_ref_directly(): void
    {
        $this->app['config']->set('luminous.wrap_responses', false);

        $spec = $this->makeGenerator()->generate();
        $schema = $spec['paths']['/v1/payments']['post']['responses']['201']['content']['application/json']['schema'];

        $this->assertArrayHasKey('$ref', $schema);
        $this->assertArrayNotHasKey('type', $schema);
    }

    public function test_with_wrap_responses_schema_is_data_envelope(): void
    {
        $this->app['config']->set('luminous.wrap_responses', true);

        $spec = $this->makeGenerator()->generate();
        $schema = $spec['paths']['/v1/payments']['post']['responses']['201']['content']['application/json']['schema'];

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('data', $schema['properties']);
        $this->assertSame(['data'], $schema['required']);
    }

    public function test_paginated_response_includes_pagination_property(): void
    {
        $this->app['config']->set('luminous.wrap_responses', true);
        $this->app['config']->set('luminous.include_pagination_schema', true);

        $spec = $this->makeGenerator()->generate();
        $schema = $spec['paths']['/v1/payments']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertArrayHasKey('pagination', $schema['properties']);
        $this->assertSame('#/components/schemas/PaginationMeta', $schema['properties']['pagination']['$ref']);
        $this->assertContains('pagination', $schema['required']);
        $this->assertArrayHasKey('PaginationMeta', $spec['components']['schemas']);
    }

    public function test_paginated_without_pagination_schema_omits_pagination_property(): void
    {
        $this->app['config']->set('luminous.wrap_responses', true);
        $this->app['config']->set('luminous.include_pagination_schema', false);

        $spec = $this->makeGenerator()->generate();
        $schema = $spec['paths']['/v1/payments']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertArrayHasKey('data', $schema['properties']);
        $this->assertSame('array', $schema['properties']['data']['type']);
        $this->assertArrayNotHasKey('pagination', $schema['properties']);
        $this->assertSame(['data'], $schema['required']);
    }

    public function test_ref_based_response_is_not_wrapped(): void
    {
        $this->app['config']->set('luminous.wrap_responses', true);

        $spec = $this->makeGenerator()->generate();

        // #[ApiResponse(401, ref: '#/components/schemas/ErrorResponse')] on index(),
        // ref-based responses are fully-specified by the caller and bypass envelope wrapping.
        $schema = $spec['paths']['/v1/payments']['get']['responses']['401']['content']['application/json']['schema'];

        $this->assertArrayHasKey('$ref', $schema);
        $this->assertSame('#/components/schemas/ErrorResponse', $schema['$ref']);
        $this->assertArrayNotHasKey('type', $schema);
    }

    public function test_custom_wrapper_key(): void
    {
        $this->app['config']->set('luminous.wrap_responses', true);
        $this->app['config']->set('luminous.response_wrapper_key', 'result');

        $spec = $this->makeGenerator()->generate();
        $schema = $spec['paths']['/v1/payments']['post']['responses']['201']['content']['application/json']['schema'];

        $this->assertArrayHasKey('result', $schema['properties']);
        $this->assertArrayNotHasKey('data', $schema['properties']);
        $this->assertSame(['result'], $schema['required']);
    }
}
