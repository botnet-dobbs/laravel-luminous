<?php

namespace Botnetdobbs\Luminous\Tests\Feature;

use Botnetdobbs\Luminous\Extractors\ControllerExtractor;
use Botnetdobbs\Luminous\Extractors\EnumExtractor;
use Botnetdobbs\Luminous\Extractors\ExtractedRoute;
use Botnetdobbs\Luminous\Extractors\RequestExtractor;
use Botnetdobbs\Luminous\Extractors\ResourceExtractor;
use Botnetdobbs\Luminous\Generator\ComponentsRegistry;
use Botnetdobbs\Luminous\LuminousServiceProvider;
use Botnetdobbs\Luminous\Support\TypeMapper;
use Botnetdobbs\Luminous\Tests\Fixtures\Controllers\IgnoredController;
use Botnetdobbs\Luminous\Tests\Fixtures\Controllers\OrderController;
use Botnetdobbs\Luminous\Tests\Fixtures\Controllers\PaymentController;
use Botnetdobbs\Luminous\Tests\Fixtures\Controllers\PlainController;
use Orchestra\Testbench\TestCase;

class ControllerExtractorTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LuminousServiceProvider::class];
    }

    private function makeExtractor(array $config = []): ControllerExtractor
    {
        $registry = new ComponentsRegistry;
        $enumExtractor = new EnumExtractor;
        $typeMapper = new TypeMapper($enumExtractor);
        $requestEx = new RequestExtractor($typeMapper, $registry, $enumExtractor);
        $resourceEx = new ResourceExtractor($typeMapper, $registry, $enumExtractor);

        return new ControllerExtractor(
            requestExtractor: $requestEx,
            resourceExtractor: $resourceEx,
            config: array_merge([
                'wrap_responses' => false,
                'response_wrapper_key' => 'data',
                'include_pagination_schema' => true,
                'default_security' => [],
            ], $config),
        );
    }

    private function route(string $method, string $path, string $controllerMethod): ExtractedRoute
    {
        return new ExtractedRoute(
            httpMethod: $method,
            path: $path,
            controllerClass: PaymentController::class,
            methodName: $controllerMethod,
            routeName: 'payment.'.$controllerMethod,
            middlewares: [],
        );
    }

    public function test_extract_produces_valid_operation_object(): void
    {
        $op = $this->makeExtractor()->extract($this->route('post', '/v1/payments', 'store'));

        $this->assertArrayHasKey('summary', $op);
        $this->assertArrayHasKey('operationId', $op);
        $this->assertArrayHasKey('tags', $op);
        $this->assertArrayHasKey('requestBody', $op);
        $this->assertArrayHasKey('responses', $op);
    }

    public function test_request_body_uses_ref_not_inline(): void
    {
        $op = $this->makeExtractor()->extract($this->route('post', '/v1/payments', 'store'));

        $schema = $op['requestBody']['content']['application/json']['schema'];
        $this->assertArrayHasKey('$ref', $schema);
        $this->assertStringStartsWith('#/components/schemas/', $schema['$ref']);
    }

    public function test_api_no_security_produces_empty_security_array(): void
    {
        $op = $this->makeExtractor()->extract($this->route('get', '/v1/payments/{id}/status', 'publicStatus'));

        $this->assertSame([], $op['security']);
    }

    public function test_class_security_cascades_to_methods(): void
    {
        $op = $this->makeExtractor()->extract($this->route('get', '/v1/payments', 'index'));

        $this->assertArrayHasKey('security', $op);
        $this->assertNotEmpty($op['security']);
    }

    public function test_deprecated_operation_has_deprecated_flag(): void
    {
        $op = $this->makeExtractor()->extract($this->route('post', '/v1/payments/legacy', 'legacyStore'));

        $this->assertTrue($op['deprecated']);
        $this->assertStringContainsString('Deprecated', $op['description']);
    }

    public function test_class_tag_appears_in_operation(): void
    {
        $op = $this->makeExtractor()->extract($this->route('get', '/v1/payments', 'index'));

        $this->assertContains('Payments', $op['tags']);
    }

    public function test_collection_response_wraps_in_array_schema(): void
    {
        // TODO : this assertion will need updating when wrap_responses envelope lands
        $op = $this->makeExtractor()->extract($this->route('get', '/v1/payments', 'index'));

        $schema = $op['responses']['200']['content']['application/json']['schema'];
        $this->assertSame('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
    }

    public function test_cancel_method_auto_detects_last_form_request(): void
    {
        $op = $this->makeExtractor()->extract($this->route('delete', '/v1/payments/{id}', 'cancel'));

        // cancel(string $id, CancelPaymentRequest $request). no #[ApiBody], should auto-detect CancelPaymentRequest
        $this->assertArrayHasKey('requestBody', $op);
        $schema = $op['requestBody']['content']['application/json']['schema']['$ref'];
        $this->assertStringContainsString('CancelPaymentRequest', $schema);
    }

    public function test_idempotency_key_header_is_documented(): void
    {
        $op = $this->makeExtractor()->extract($this->route('post', '/v1/payments', 'store'));

        $headerParam = collect($op['parameters'] ?? [])
            ->first(fn ($p) => $p['in'] === 'header' && $p['name'] === 'Idempotency-Key');

        $this->assertNotNull($headerParam);
        $this->assertTrue($headerParam['required']);
    }

    public function test_500_added_unless_explicitly_declared(): void
    {
        $op = $this->makeExtractor()->extract($this->route('get', '/v1/payments', 'index'));

        $this->assertArrayHasKey('500', $op['responses']);
    }

    public function test_request_example_attached_to_request_body(): void
    {
        $op = $this->makeExtractor()->extract($this->route('post', '/v1/payments', 'store'));

        $examples = $op['requestBody']['content']['application/json']['examples'] ?? [];
        $this->assertArrayHasKey('usd-payment', $examples);
    }

    public function test_class_level_api_ignore_returns_empty_array(): void
    {
        $route = new ExtractedRoute(
            httpMethod: 'get',
            path: '/internal',
            controllerClass: IgnoredController::class,
            methodName: 'index',
            routeName: 'internal.index',
            middlewares: [],
        );

        $this->assertSame([], $this->makeExtractor()->extract($route));
    }

    public function test_composed_of_with_for_status_applies_schema_to_target_response(): void
    {
        $op = $this->makeExtractor()->extract($this->route('post', '/v1/payments/merge', 'merge'));

        $schema = $op['responses']['200']['content']['application/json']['schema'];
        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertCount(2, $schema['oneOf']);
    }

    public function test_composed_of_without_for_status_applies_to_first_description_only_response(): void
    {
        $op = $this->makeExtractor()->extract($this->route('get', '/v1/payments/options', 'paymentOptions'));

        $schema = $op['responses']['200']['content']['application/json']['schema'];
        $this->assertArrayHasKey('anyOf', $schema);
        $this->assertCount(2, $schema['anyOf']);
    }

    public function test_default_security_config_used_when_no_security_attributes(): void
    {
        $defaultSec = [['apiKey' => []]];
        $extractor = $this->makeExtractor(['default_security' => $defaultSec]);
        $route = new ExtractedRoute(
            httpMethod: 'get',
            path: '/plain',
            controllerClass: PlainController::class,
            methodName: 'index',
            routeName: 'plain.index',
            middlewares: [],
        );

        $this->assertSame($defaultSec, $extractor->extract($route)['security']);
    }

    public function test_api_query_enum_appears_in_parameter_schema(): void
    {
        $op = $this->makeExtractor()->extract($this->route('get', '/v1/payments', 'index'));

        $statusParam = collect($op['parameters'] ?? [])
            ->first(fn ($p) => $p['name'] === 'status');

        $this->assertNotNull($statusParam);
        $this->assertArrayHasKey('enum', $statusParam['schema']);
        $this->assertContains('succeeded', $statusParam['schema']['enum']);
    }

    public function test_api_response_with_ref_uses_ref_path_directly(): void
    {
        $op = $this->makeExtractor()->extract($this->route('get', '/v1/payments', 'index'));

        $schema = $op['responses']['401']['content']['application/json']['schema'];
        $this->assertSame('#/components/schemas/ErrorResponse', $schema['$ref']);
    }

    public function test_path_params_are_auto_detected_from_route_uri(): void
    {
        $route = new ExtractedRoute('get', '/orders/{orderId}', OrderController::class, 'show', 'order.show', []);
        $op = $this->makeExtractor()->extract($route);

        $pathParam = collect($op['parameters'] ?? [])->firstWhere('name', 'orderId');

        $this->assertNotNull($pathParam, 'orderId path parameter was not generated');
        $this->assertSame('path', $pathParam['in']);
        $this->assertTrue($pathParam['required']);
        $this->assertSame('integer', $pathParam['schema']['type']);
    }

    public function test_multiple_path_params_are_all_auto_detected(): void
    {
        $route = new ExtractedRoute('get', '/orders/{orderId}/items/{itemId}', OrderController::class, 'item', 'order.item', []);
        $op = $this->makeExtractor()->extract($route);

        $names = collect($op['parameters'] ?? [])->where('in', 'path')->pluck('name')->all();

        $this->assertContains('orderId', $names);
        $this->assertContains('itemId', $names);
    }

    public function test_explicit_api_param_overrides_auto_detection(): void
    {
        $op = $this->makeExtractor()->extract($this->route('get', '/v1/payments/{id}', 'show'));

        $idParams = collect($op['parameters'] ?? [])->where('name', 'id')->where('in', 'path')->values()->all();

        $this->assertCount(1, $idParams, 'id must appear exactly once, not duplicated by auto-detection');
        $this->assertSame('uuid', $idParams[0]['schema']['format'] ?? null, 'Explicit format from #[ApiParam] must be preserved');
    }
}
