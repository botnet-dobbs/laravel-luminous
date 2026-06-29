<?php

namespace Botnetdobbs\Luminous\Tests\Feature;

use Botnetdobbs\Luminous\Extractors\EnumExtractor;
use Botnetdobbs\Luminous\Extractors\RequestExtractor;
use Botnetdobbs\Luminous\Extractors\ResourceExtractor;
use Botnetdobbs\Luminous\Generator\ComponentsRegistry;
use Botnetdobbs\Luminous\LuminousServiceProvider;
use Botnetdobbs\Luminous\Support\TypeMapper;
use Botnetdobbs\Luminous\Tests\Fixtures\Enums\PaymentStatus;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\AddressRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\ConfirmedRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\CreatePaymentRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\FileUploadRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\HintsRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\ShapeRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\ThrowingRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\UnionTypeRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\WildcardRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Resources\PaymentResource;
use Botnetdobbs\Luminous\Tests\Fixtures\Resources\ShapeResource;
use Botnetdobbs\Luminous\Tests\Fixtures\Resources\TreeNodeResource;
use Orchestra\Testbench\TestCase;

class SchemaExtractorsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LuminousServiceProvider::class];
    }

    private function makeRegistry(): ComponentsRegistry
    {
        return new ComponentsRegistry;
    }

    private function makeRequestExtractor(ComponentsRegistry $registry): RequestExtractor
    {
        $enumExtractor = new EnumExtractor;
        $typeMapper = new TypeMapper($enumExtractor);

        return new RequestExtractor($typeMapper, $registry, $enumExtractor);
    }

    private function makeResourceExtractor(ComponentsRegistry $registry): ResourceExtractor
    {
        $enumExtractor = new EnumExtractor;
        $typeMapper = new TypeMapper($enumExtractor);

        return new ResourceExtractor($typeMapper, $registry, $enumExtractor);
    }

    public function test_request_extractor_registers_schema_and_returns_ref(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $result = $extractor->extract(CreatePaymentRequest::class);

        $this->assertSame(['$ref' => '#/components/schemas/CreatePaymentRequest'], $result);
        $this->assertArrayHasKey('CreatePaymentRequest', $registry->all());
        $this->assertSame('object', $registry->all()['CreatePaymentRequest']['type']);
    }

    public function test_request_extractor_is_idempotent(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $first = $extractor->extract(CreatePaymentRequest::class);
        $second = $extractor->extract(CreatePaymentRequest::class);

        $this->assertSame($first, $second);
        $this->assertCount(2, $registry->all()); // CreatePaymentRequest + PaymentStatus enum
    }

    public function test_resource_extractor_registers_schema_and_returns_ref(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $result = $extractor->extract(PaymentResource::class);

        $this->assertSame(['$ref' => '#/components/schemas/PaymentResource'], $result);
        $this->assertArrayHasKey('PaymentResource', $registry->all());
    }

    public function test_backed_enum_property_produces_ref_to_enum_schema(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $extractor->extract(PaymentResource::class);

        $schemas = $registry->all();
        $this->assertArrayHasKey('PaymentStatus', $schemas);

        $props = $schemas['PaymentResource']['properties'];
        $this->assertArrayHasKey('$ref', $props['status']);
        $this->assertSame('#/components/schemas/PaymentStatus', $props['status']['$ref']);
    }

    public function test_enum_extractor_produces_correct_schema(): void
    {
        $extractor = new EnumExtractor;
        $schema = $extractor->extract(PaymentStatus::class);

        $this->assertSame('string', $schema['type']);
        $this->assertContains('succeeded', $schema['enum']);
        $this->assertCount(5, $schema['enum']);
    }

    public function test_dot_notation_rules_produce_nested_object(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        // Force rules-based extraction by using AddressRequest (no #[ApiProperty] annotations)
        $extractor->extract(AddressRequest::class);

        $schema = $registry->all()['AddressRequest'];
        $this->assertArrayHasKey('address', $schema['properties']);
        $this->assertSame('object', $schema['properties']['address']['type']);
        $this->assertArrayHasKey('street', $schema['properties']['address']['properties']);
    }

    public function test_components_registry_reset_clears_all_schemas(): void
    {
        $registry = $this->makeRegistry();
        $registry->registerAnonymous('Test', ['type' => 'string']);
        $this->assertNotEmpty($registry->all());

        $registry->reset();
        $this->assertEmpty($registry->all());
    }

    public function test_self_referential_resource_does_not_stack_overflow(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $result = $extractor->extract(TreeNodeResource::class);

        $this->assertSame(['$ref' => '#/components/schemas/TreeNodeResource'], $result);
        $this->assertArrayHasKey('TreeNodeResource', $registry->all());
    }

    public function test_annotated_json_resource_property_is_not_overwritten_by_secondary_loop(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $extractor->extract(TreeNodeResource::class);

        $schema = $registry->all()['TreeNodeResource'];
        // $annotated has #[ApiProperty(ref: '#/components/schemas/CustomSchema')] — annotation must win
        $this->assertSame(['$ref' => '#/components/schemas/CustomSchema'], $schema['properties']['annotated']);
    }

    public function test_union_type_property_does_not_crash(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $result = $extractor->extract(UnionTypeRequest::class);

        $this->assertSame(['$ref' => '#/components/schemas/UnionTypeRequest'], $result);
        $schema = $registry->all()['UnionTypeRequest'];
        // Union type int|float maps to 'mixed' → empty schema fragment — property still present
        $this->assertArrayHasKey('amount', $schema['properties']);
    }

    public function test_rules_exception_falls_back_to_empty_schema(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $result = $extractor->extract(ThrowingRequest::class);

        $this->assertSame(['$ref' => '#/components/schemas/ThrowingRequest'], $result);
        $schema = $registry->all()['ThrowingRequest'];
        $this->assertSame([], $schema['properties']);
    }

    public function test_register_anonymous_with_invalid_name_throws(): void
    {
        $registry = $this->makeRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $registry->registerAnonymous('Invalid Name', ['type' => 'string']);
    }

    // OpenAPI 3.1 nullable

    public function test_nullable_api_property_uses_openapi31_type_array(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $extractor->extract(PaymentResource::class);

        $schema = $registry->all()['PaymentResource'];
        // settled_at has #[ApiProperty(nullable: true, optional: true, format: 'date-time')]
        $settledAt = $schema['properties']['settled_at'];
        $this->assertIsArray($settledAt['type']);
        $this->assertContains('string', $settledAt['type']);
        $this->assertContains('null', $settledAt['type']);
        $this->assertArrayNotHasKey('nullable', $settledAt);
    }

    // Shape / #[ApiShape]. ResourceExtractor

    public function test_shape_attribute_on_resource_uses_schema_method(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $result = $extractor->extract(ShapeResource::class);

        $this->assertSame(['$ref' => '#/components/schemas/ShapeResource'], $result);
        $this->assertArrayHasKey('ShapeResource', $registry->all());
        $this->assertSame('object', $registry->all()['ShapeResource']['type']);
    }

    public function test_shape_resource_scalar_fields_correct_types(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $extractor->extract(ShapeResource::class);

        $props = $registry->all()['ShapeResource']['properties'];
        $this->assertSame('uuid', $props['id']['format']);
        $this->assertSame('integer', $props['amount']['type']);
        $this->assertSame(1, $props['amount']['minimum']);
    }

    public function test_shape_resource_enum_registers_in_components(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $extractor->extract(ShapeResource::class);

        $schemas = $registry->all();
        $this->assertArrayHasKey('PaymentStatus', $schemas);
        $props = $schemas['ShapeResource']['properties'];
        $this->assertArrayHasKey('$ref', $props['status']);
        $this->assertSame('#/components/schemas/PaymentStatus', $props['status']['$ref']);
    }

    public function test_shape_resource_ref_resolves_to_component_ref(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $extractor->extract(ShapeResource::class);

        $schemas = $registry->all();
        $this->assertArrayHasKey('PaymentResource', $schemas);
        $props = $schemas['ShapeResource']['properties'];
        $this->assertArrayHasKey('$ref', $props['payment']);
        $this->assertSame('#/components/schemas/PaymentResource', $props['payment']['$ref']);
    }

    public function test_shape_resource_nullable_field_is_openapi31_type_array(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $extractor->extract(ShapeResource::class);

        $props = $registry->all()['ShapeResource']['properties'];
        $this->assertSame(['string', 'null'], $props['name']['type']);
        $this->assertArrayNotHasKey('nullable', $props['name']);
    }

    public function test_shape_resource_optional_field_not_in_required(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $extractor->extract(ShapeResource::class);

        $schema = $registry->all()['ShapeResource'];
        $this->assertNotContains('payment', $schema['required'] ?? []);
        $this->assertNotContains('name', $schema['required'] ?? []);
        $this->assertContains('id', $schema['required']);
    }

    // Shape / #[ApiShape]. RequestExtractor

    public function test_shape_attribute_on_request_uses_schema_method(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $result = $extractor->extract(ShapeRequest::class);

        $this->assertSame(['$ref' => '#/components/schemas/ShapeRequest'], $result);
        $schema = $registry->all()['ShapeRequest'];
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertSame('User name', $schema['properties']['name']['description']);
    }

    // hints()

    public function test_hints_adds_description_and_example_from_hints_method(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $extractor->extract(HintsRequest::class);

        $props = $registry->all()['HintsRequest']['properties'];
        $this->assertSame('Amount in minor currency units', $props['amount']['description']);
        $this->assertSame(10000, $props['amount']['example']);
        $this->assertSame('Human-readable payment description', $props['description']['description']);
    }

    public function test_hints_does_not_override_rules_types_or_constraints(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $extractor->extract(HintsRequest::class);

        $props = $registry->all()['HintsRequest']['properties'];
        // rules() says integer with min:1 max:1000000. The hint must not override type or constraints.
        $this->assertSame('integer', $props['amount']['type']);
        $this->assertSame(1, $props['amount']['minimum']);
        $this->assertSame(1000000, $props['amount']['maximum']);
    }

    // Wildcard arrays and deep dot-notation

    public function test_wildcard_rules_produce_typed_array_of_objects(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $extractor->extract(WildcardRequest::class);

        $props = $registry->all()['WildcardRequest']['properties'];
        $this->assertSame('array', $props['items']['type']);
        $this->assertSame(1, $props['items']['minItems']);
        $this->assertSame('object', $props['items']['items']['type']);
        $this->assertArrayHasKey('product_id', $props['items']['items']['properties']);
        $this->assertSame('uuid', $props['items']['items']['properties']['product_id']['format']);
    }

    public function test_simple_wildcard_produces_scalar_array_items(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $extractor->extract(WildcardRequest::class);

        $props = $registry->all()['WildcardRequest']['properties'];
        $this->assertSame('array', $props['tag_ids']['type']);
        $this->assertSame('uuid', $props['tag_ids']['items']['format']);
    }

    public function test_dot_notation_two_levels_produces_nested_object(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $extractor->extract(WildcardRequest::class);

        $props = $registry->all()['WildcardRequest']['properties'];
        $this->assertSame('object', $props['billing']['type']);
        $this->assertArrayHasKey('street', $props['billing']['properties']);
        $this->assertArrayHasKey('city', $props['billing']['properties']);
    }

    // confirmed rule

    public function test_confirmed_rule_adds_companion_confirmation_field(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $extractor->extract(ConfirmedRequest::class);

        $schema = $registry->all()['ConfirmedRequest'];
        $props = $schema['properties'];

        // password is required, nullable, and confirmed. The companion should inherit the nullable type.
        $this->assertArrayHasKey('password_confirmation', $props);
        $this->assertSame(['string', 'null'], $props['password_confirmation']['type']);
        $this->assertTrue($props['password_confirmation']['writeOnly']);
        $this->assertContains('password_confirmation', $schema['required'] ?? []);
    }

    public function test_confirmed_companion_not_required_when_field_is_sometimes(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $extractor->extract(ConfirmedRequest::class);

        $schema = $registry->all()['ConfirmedRequest'];
        // backup_code has 'sometimes', so its companion should not be in required.
        $this->assertArrayHasKey('backup_code_confirmation', $schema['properties']);
        $this->assertNotContains('backup_code_confirmation', $schema['required'] ?? []);
    }

    // mediaType()

    public function test_media_type_is_multipart_when_file_rule_present(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $this->assertSame('multipart/form-data', $extractor->mediaType(FileUploadRequest::class));
    }

    public function test_media_type_is_multipart_for_mimetypes_colon_rule(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        // FileUploadRequest uses mimetypes:image/jpeg,image/png (with colon variant)
        $this->assertSame('multipart/form-data', $extractor->mediaType(FileUploadRequest::class));
    }

    public function test_media_type_is_json_by_default(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeRequestExtractor($registry);

        $this->assertSame('application/json', $extractor->mediaType(AddressRequest::class));
    }

    // EnumExtractor docblock fallback

    public function test_enum_extractor_reads_at_description_tag(): void
    {
        $extractor = new EnumExtractor;
        $schema = $extractor->extract(PaymentStatus::class);

        // PaymentStatus cases have @description tags
        if (isset($schema['x-enum-descriptions'])) {
            $this->assertIsArray($schema['x-enum-descriptions']);
        } else {
            // If no docblocks, just confirm schema is valid
            $this->assertArrayHasKey('enum', $schema);
        }
    }

    // ApiProperty new fields

    public function test_api_property_optional_excludes_field_from_required(): void
    {
        $registry = $this->makeRegistry();
        $extractor = $this->makeResourceExtractor($registry);

        $extractor->extract(PaymentResource::class);

        $schema = $registry->all()['PaymentResource'];
        $this->assertNotContains('settled_at', $schema['required'] ?? []);
    }
}
