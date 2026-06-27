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

class OpenApiGeneratorTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LuminousServiceProvider::class];
    }

    protected function defineRoutes($router): void
    {
        $router->prefix('v1')->group(function () use ($router) {
            $router->get('/payments', [PaymentController::class, 'index'])->name('payment.index');
            $router->post('/payments', [PaymentController::class, 'store'])->name('payment.store');
            $router->get('/payments/{id}', [PaymentController::class, 'show'])->name('payment.show');
            $router->delete('/payments/{id}', [PaymentController::class, 'cancel'])->name('payment.cancel');
            $router->post('/payments/merge', [PaymentController::class, 'merge'])->name('payment.merge');
            $router->get('/payments/options', [PaymentController::class, 'paymentOptions'])->name('payment.paymentOptions');
        });
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('luminous.enabled', true);
        $app['config']->set('luminous.include_pagination_schema', true);
        $app['config']->set('luminous.security_schemes', [
            'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
        ]);
    }

    private function makeGenerator(?ComponentsRegistry $registry = null): OpenApiGenerator
    {
        $config = config('luminous');
        $registry ??= new ComponentsRegistry;
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

    public function test_generates_valid_openapi_3_1_structure(): void
    {
        $spec = $this->makeGenerator()->generate();

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('tags', $spec);
        $this->assertArrayHasKey('servers', $spec);
    }

    public function test_info_uses_config_defaults(): void
    {
        $spec = $this->makeGenerator()->generate();

        $this->assertSame('Luminous API', $spec['info']['title']);
        $this->assertSame('1.0.0', $spec['info']['version']);
        $this->assertArrayNotHasKey('description', $spec['info']);
        $this->assertArrayNotHasKey('contact', $spec['info']);
        $this->assertArrayNotHasKey('license', $spec['info']);
    }

    public function test_components_schemas_is_populated(): void
    {
        $spec = $this->makeGenerator()->generate();

        $this->assertArrayHasKey('schemas', $spec['components']);
        $this->assertArrayHasKey('ErrorResponse', $spec['components']['schemas']);
        $this->assertArrayHasKey('PaginationMeta', $spec['components']['schemas']);

        // OpenAPI 3.1 uses type union. Not nullable: true
        $cursor = $spec['components']['schemas']['PaginationMeta']['properties']['cursor'];
        $this->assertSame(['string', 'null'], $cursor['type']);
    }

    public function test_pagination_schema_absent_when_disabled(): void
    {
        $this->app['config']->set('luminous.include_pagination_schema', false);

        $spec = $this->makeGenerator()->generate();

        $this->assertArrayNotHasKey('PaginationMeta', $spec['components']['schemas']);
    }

    public function test_request_body_schema_uses_ref_not_inline(): void
    {
        $spec = $this->makeGenerator()->generate();

        $schema = $spec['paths']['/v1/payments']['post']['requestBody']['content']['application/json']['schema'];
        $this->assertArrayHasKey('$ref', $schema);
        $this->assertStringStartsWith('#/components/schemas/', $schema['$ref']);
    }

    public function test_paths_are_sorted_alphabetically(): void
    {
        $spec = $this->makeGenerator()->generate();

        $paths = array_keys($spec['paths']);
        $sorted = collect($paths)->sort()->values()->all();

        $this->assertSame($sorted, $paths);
    }

    public function test_registry_is_reset_between_calls(): void
    {
        $registry = new ComponentsRegistry;
        $generator = $this->makeGenerator($registry);

        $generator->generate();
        $countAfterFirst = count($registry->all());

        $generator->generate();
        $countAfterSecond = count($registry->all());

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_failed_route_extraction_is_skipped_not_thrown(): void
    {
        // PaymentController exists but 'ghost' method does not. ReflectionException inside generate()'s try/catch
        $this->app['router']->get('/bad', [PaymentController::class, 'ghost'])->name('bad.test');

        $spec = $this->makeGenerator()->generate();

        $this->assertArrayNotHasKey('/bad', $spec['paths']);
    }

    public function test_tags_appear_at_top_level_and_are_sorted(): void
    {
        $spec = $this->makeGenerator()->generate();

        $tagNames = collect($spec['tags'])->pluck('name')->all();
        $this->assertContains('Payments', $tagNames);

        $sorted = collect($tagNames)->sort()->values()->all();
        $this->assertSame($sorted, $tagNames);
    }

    public function test_security_schemes_appear_in_components(): void
    {
        $spec = $this->makeGenerator()->generate();

        $this->assertArrayHasKey('securitySchemes', $spec['components']);
        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
    }
}
