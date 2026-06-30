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

class FullSpecGenerationTest extends TestCase
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
            $router->post('/payments/legacy', [PaymentController::class, 'legacyStore'])->name('payment.legacy');
            $router->get('/payments/{id}/status', [PaymentController::class, 'publicStatus'])->name('payment.publicStatus');
            $router->get('/payments/internal-dump', [PaymentController::class, 'internalDump'])->name('payment.internal');
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
        $routeEx = new RouteExtractor($config, $this->app['router']);

        return new OpenApiGenerator(
            config: $config,
            routeExtractor: $routeEx,
            controllerExtractor: $controllerEx,
            registry: $registry,
        );
    }

    public function test_generates_complete_and_correct_spec(): void
    {
        $spec = $this->makeGenerator()->generate();

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);

        $this->assertArrayHasKey('/v1/payments', $spec['paths']);
        $this->assertArrayHasKey('/v1/payments/{id}', $spec['paths']);

        $post = $spec['paths']['/v1/payments']['post'];
        foreach (['summary', 'operationId', 'tags', 'parameters', 'requestBody', 'responses', 'security'] as $key) {
            $this->assertArrayHasKey($key, $post, "POST /v1/payments missing key: {$key}");
        }

        $bodySchema = $post['requestBody']['content']['application/json']['schema'];
        $this->assertArrayHasKey('$ref', $bodySchema);
        $this->assertStringStartsWith('#/components/schemas/', $bodySchema['$ref']);

        $headers = collect($post['parameters'])->where('in', 'header')->values();
        $headerNames = $headers->pluck('name')->all();
        $this->assertContains('Idempotency-Key', $headerNames);
        $idempotencyHeader = $headers->firstWhere('name', 'Idempotency-Key');
        $this->assertTrue($idempotencyHeader['required']);

        foreach (['201', '409', '422', '500'] as $status) {
            $this->assertArrayHasKey($status, $post['responses'], "Missing response {$status}");
        }

        $this->assertArrayHasKey('examples', $post['requestBody']['content']['application/json']);
        $this->assertArrayHasKey('usd-payment', $post['requestBody']['content']['application/json']['examples']);

        $tagNames = collect($spec['tags'])->pluck('name')->all();
        $this->assertContains('Payments', $tagNames);

        foreach (['ErrorResponse', 'PaginationMeta', 'CreatePaymentRequest', 'PaymentResource', 'PaymentStatus'] as $schema) {
            $this->assertArrayHasKey($schema, $spec['components']['schemas'], "Missing schema: {$schema}");
        }

        $this->assertContains('succeeded', $spec['components']['schemas']['PaymentStatus']['enum']);
    }

    public function test_api_ignore_excludes_route_from_spec(): void
    {
        $spec = $this->makeGenerator()->generate();
        $allPaths = implode('|', array_keys($spec['paths']));

        $this->assertStringNotContainsString('internal-dump', $allPaths);
    }

    public function test_deprecated_operation_is_marked(): void
    {
        $spec = $this->makeGenerator()->generate();

        $legacyOp = collect($spec['paths'])
            ->flatMap(fn ($methods) => collect($methods)->values()->all())
            ->first(fn ($op) => is_array($op) && str_contains($op['description'] ?? '', 'Deprecated'));

        $this->assertNotNull($legacyOp, 'No deprecated operation found in spec');
        $this->assertTrue($legacyOp['deprecated']);
    }

    public function test_api_no_security_produces_empty_security_array(): void
    {
        $spec = $this->makeGenerator()->generate();
        $publicOp = $spec['paths']['/v1/payments/{id}/status']['get'];

        $this->assertSame([], $publicOp['security']);
    }

    public function test_backed_enum_property_type_is_ref(): void
    {
        $spec = $this->makeGenerator()->generate();
        $props = $spec['components']['schemas']['PaymentResource']['properties'];

        $this->assertArrayHasKey('$ref', $props['status']);
        $this->assertSame('#/components/schemas/PaymentStatus', $props['status']['$ref']);
    }

    public function test_type_mapper_produces_correct_constraints(): void
    {
        $spec = $this->makeGenerator()->generate();
        $schema = $spec['components']['schemas']['CreatePaymentRequest'];

        $this->assertSame(1, $schema['properties']['amount']['minimum']);
        $this->assertArrayNotHasKey('minLength', $schema['properties']['amount']);

        $this->assertSame(500, $schema['properties']['description']['maxLength']);
        $this->assertArrayNotHasKey('maximum', $schema['properties']['description']);
    }
}
