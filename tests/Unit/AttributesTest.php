<?php

namespace Botnetdobbs\Luminous\Tests\Unit;

use Botnetdobbs\Luminous\Attributes\ApiBody;
use Botnetdobbs\Luminous\Attributes\ApiComposedOf;
use Botnetdobbs\Luminous\Attributes\ApiExample;
use Botnetdobbs\Luminous\Attributes\ApiHeader;
use Botnetdobbs\Luminous\Attributes\ApiIgnore;
use Botnetdobbs\Luminous\Attributes\ApiItems;
use Botnetdobbs\Luminous\Attributes\ApiNoSecurity;
use Botnetdobbs\Luminous\Attributes\ApiOperation;
use Botnetdobbs\Luminous\Attributes\ApiParam;
use Botnetdobbs\Luminous\Attributes\ApiProperty;
use Botnetdobbs\Luminous\Attributes\ApiQuery;
use Botnetdobbs\Luminous\Attributes\ApiResponse;
use Botnetdobbs\Luminous\Attributes\ApiResponseHeader;
use Botnetdobbs\Luminous\Attributes\ApiSecurity;
use Botnetdobbs\Luminous\Attributes\ApiStream;
use Botnetdobbs\Luminous\Attributes\ApiTag;
use Botnetdobbs\Luminous\Tests\Fixtures\Controllers\TestAttributeController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttributesTest extends TestCase
{
    public function test_repeatable_attributes_return_all_instances(): void
    {
        $ref = new \ReflectionMethod(TestAttributeController::class, 'store');

        $this->assertCount(3, $ref->getAttributes(ApiResponse::class));
        $this->assertCount(2, $ref->getAttributes(ApiQuery::class));
        $this->assertCount(1, $ref->getAttributes(ApiHeader::class));

        $responses = array_map(
            fn ($a) => $a->newInstance(),
            $ref->getAttributes(ApiResponse::class)
        );

        $this->assertSame(201, $responses[0]->status);
        $this->assertSame(409, $responses[1]->status);
        $this->assertSame(422, $responses[2]->status);
    }

    public function test_api_no_security_overrides_class_level_security(): void
    {
        $classRef = new \ReflectionClass(TestAttributeController::class);
        $methodRef = $classRef->getMethod('publicStatus');

        $this->assertNotEmpty($classRef->getAttributes(ApiSecurity::class));
        $this->assertNotEmpty($methodRef->getAttributes(ApiNoSecurity::class));
    }

    public function test_api_ignore_present_on_internal_method(): void
    {
        $ref = new \ReflectionMethod(TestAttributeController::class, 'internalMethod');

        $this->assertNotEmpty($ref->getAttributes(ApiIgnore::class));
    }

    public function test_api_operation_is_not_repeatable(): void
    {
        $flags = (new \ReflectionClass(ApiOperation::class))
            ->getAttributes(\Attribute::class)[0]
            ->newInstance();

        $this->assertSame(0, $flags->flags & \Attribute::IS_REPEATABLE);
    }

    public function test_api_tag_is_repeatable_on_class_and_method(): void
    {
        $flags = (new \ReflectionClass(ApiTag::class))
            ->getAttributes(\Attribute::class)[0]
            ->newInstance();

        $this->assertSame(\Attribute::IS_REPEATABLE, $flags->flags & \Attribute::IS_REPEATABLE);
        $this->assertSame(\Attribute::TARGET_CLASS, $flags->flags & \Attribute::TARGET_CLASS);
        $this->assertSame(\Attribute::TARGET_METHOD, $flags->flags & \Attribute::TARGET_METHOD);
    }

    public function test_api_operation_defaults(): void
    {
        $op = new ApiOperation('Test summary');

        $this->assertSame('Test summary', $op->summary);
        $this->assertSame('', $op->description);
        $this->assertNull($op->operationId);
    }

    public function test_api_property_full_field_set(): void
    {
        $prop = new ApiProperty(
            description: 'Amount',
            example: 100,
            format: 'int32',
            nullable: false,
            minimum: 1,
            maximum: 1000,
            minLength: 2,
            maxLength: 10,
            enum: ['a', 'b'],
            readOnly: true,
            writeOnly: false,
            ref: '#/components/schemas/Foo',
            itemsRef: '#/components/schemas/Bar',
            itemsType: 'string',
        );

        $this->assertSame('Amount', $prop->description);
        $this->assertSame(1, $prop->minimum);
        $this->assertSame(1000, $prop->maximum);
        $this->assertSame(2, $prop->minLength);
        $this->assertSame(10, $prop->maxLength);
        $this->assertSame(['a', 'b'], $prop->enum);
        $this->assertTrue($prop->readOnly);
        $this->assertSame('#/components/schemas/Foo', $prop->ref);
        $this->assertSame('#/components/schemas/Bar', $prop->itemsRef);
        $this->assertSame('string', $prop->itemsType);
    }

    public function test_api_body_constructor_round_trip(): void
    {
        $body = new ApiBody('App\\Http\\Requests\\CreatePaymentRequest', 'Create payload', true, 'multipart/form-data');

        $this->assertSame('App\\Http\\Requests\\CreatePaymentRequest', $body->request);
        $this->assertSame('Create payload', $body->description);
        $this->assertTrue($body->required);
        $this->assertSame('multipart/form-data', $body->mediaType);
    }

    public function test_api_body_media_type_defaults_to_null(): void
    {
        $body = new ApiBody('App\\Http\\Requests\\CreatePaymentRequest');

        $this->assertNull($body->mediaType);
    }

    public function test_api_param_constructor_round_trip(): void
    {
        $param = new ApiParam('id', 'Resource UUID', 'string', 'uuid', '550e8400');

        $this->assertSame('id', $param->name);
        $this->assertSame('string', $param->type);
        $this->assertSame('uuid', $param->format);
        $this->assertSame('550e8400', $param->example);
    }

    public static function deprecatedFieldProvider(): array
    {
        return [
            'ApiParam defaults to false' => [new ApiParam('id'), false],
            'ApiParam accepts true'      => [new ApiParam('id', deprecated: true), true],
            'ApiQuery defaults to false' => [new ApiQuery('page'), false],
            'ApiQuery accepts true'      => [new ApiQuery('legacyFilter', deprecated: true), true],
        ];
    }

    #[DataProvider('deprecatedFieldProvider')]
    public function test_deprecated_field_defaults_and_accepts_true(object $attr, bool $expected): void
    {
        $this->assertSame($expected, $attr->deprecated);
    }

    public function test_api_items_constructor_round_trip(): void
    {
        $items = new ApiItems(ref: '#/components/schemas/OrderItem', type: null, format: null, enum: ['x', 'y']);

        $this->assertSame('#/components/schemas/OrderItem', $items->ref);
        $this->assertNull($items->type);
        $this->assertSame(['x', 'y'], $items->enum);
    }

    public function test_api_composed_of_for_status_and_assert(): void
    {
        $composed = new ApiComposedOf('oneOf', ['A::class', 'B::class'], 201);

        $this->assertSame('oneOf', $composed->composition);
        $this->assertSame(['A::class', 'B::class'], $composed->refs);
        $this->assertSame(201, $composed->forStatus);
    }

    public function test_api_response_is_collection_considers_paginated(): void
    {
        $plain = new ApiResponse(200, resource: 'Foo');
        $collected = new ApiResponse(200, resource: 'Foo', collection: true);
        $paginated = new ApiResponse(200, resource: 'Foo', paginated: true);

        $this->assertFalse($plain->isCollection());
        $this->assertTrue($collected->isCollection());
        $this->assertTrue($paginated->isCollection());
    }

    public function test_api_header_has_type_and_format_separate(): void
    {
        $header = new ApiHeader('X-Request-ID', required: true, type: 'string', format: 'uuid');

        $this->assertSame('string', $header->type);
        $this->assertSame('uuid', $header->format);
    }

    public function test_api_tag_enhanced_fields_default_correctly(): void
    {
        $tag = new ApiTag('Payments');

        $this->assertSame('Payments', $tag->name);
        $this->assertSame('', $tag->description);
        $this->assertSame('', $tag->summary);
        $this->assertNull($tag->parent);
        $this->assertSame('', $tag->kind);
    }

    public function test_api_tag_accepts_all_32_fields(): void
    {
        $tag = new ApiTag('Billing', 'Billing ops', 'Handle billing', 'Finance', 'group');

        $this->assertSame('Billing', $tag->name);
        $this->assertSame('Billing ops', $tag->description);
        $this->assertSame('Handle billing', $tag->summary);
        $this->assertSame('Finance', $tag->parent);
        $this->assertSame('group', $tag->kind);
    }

    public function test_api_stream_defaults_to_sse_media_type(): void
    {
        $stream = new ApiStream('App\\Events\\PaymentEvent');

        $this->assertSame('App\\Events\\PaymentEvent', $stream->schema);
        $this->assertSame(200, $stream->status);
        $this->assertSame('text/event-stream', $stream->mediaType);
        $this->assertSame('', $stream->description);
    }

    public function test_api_stream_constructor_round_trip(): void
    {
        $stream = new ApiStream('App\\Resources\\FooResource', 'application/jsonl', 202, 'JSONL feed');

        $this->assertSame('App\\Resources\\FooResource', $stream->schema);
        $this->assertSame('application/jsonl', $stream->mediaType);
        $this->assertSame(202, $stream->status);
        $this->assertSame('JSONL feed', $stream->description);
    }

    public function test_api_stream_is_not_repeatable_on_method(): void
    {
        $flags = (new \ReflectionClass(ApiStream::class))
            ->getAttributes(\Attribute::class)[0]
            ->newInstance();

        $this->assertSame(0, $flags->flags & \Attribute::IS_REPEATABLE);
        $this->assertSame(\Attribute::TARGET_METHOD, $flags->flags & \Attribute::TARGET_METHOD);
    }

    public function test_api_query_location_defaults_to_query(): void
    {
        $query = new ApiQuery('page');

        $this->assertSame('query', $query->location);
    }

    public function test_api_query_location_accepts_querystring(): void
    {
        $query = new ApiQuery('filters', location: 'querystring');

        $this->assertSame('querystring', $query->location);
    }

    public static function apiExampleMutualExclusionProvider(): array
    {
        return [
            'value and dataValue'          => [fn () => new ApiExample('ex', value: ['key' => 'val'], dataValue: ['key' => 'val'])],
            'value and serializedValue'    => [fn () => new ApiExample('ex', value: 'raw', serializedValue: 'raw')],
            'serializedValue and external' => [fn () => new ApiExample('ex', externalValue: 'https://example.com/ex.json', serializedValue: 'raw')],
        ];
    }

    #[DataProvider('apiExampleMutualExclusionProvider')]
    public function test_api_example_mutually_exclusive_fields_throw(\Closure $factory): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mutually exclusive');
        $factory();
    }

    public function test_api_example_external_value_emitted_in_to_example_object(): void
    {
        $example = new ApiExample('ex', summary: 'An example', externalValue: 'https://example.com/ex.json');

        $obj = $example->toExampleObject();

        $this->assertSame('https://example.com/ex.json', $obj['externalValue']);
        $this->assertArrayNotHasKey('value', $obj);
    }

    public function test_api_example_data_value_emitted_in_to_example_object(): void
    {
        $example = new ApiExample('ex', dataValue: ['id' => 1]);

        $obj = $example->toExampleObject();

        $this->assertSame(['id' => 1], $obj['dataValue']);
        $this->assertArrayNotHasKey('value', $obj);
    }

    public function test_api_example_serialized_value_takes_priority(): void
    {
        $example = new ApiExample('ex', serializedValue: 'id=1&name=foo');

        $obj = $example->toExampleObject();

        $this->assertSame('id=1&name=foo', $obj['serializedValue']);
        $this->assertArrayNotHasKey('value', $obj);
        $this->assertArrayNotHasKey('dataValue', $obj);
    }

    public function test_api_response_header_constructor_round_trip(): void
    {
        $header = new ApiResponseHeader(201, 'Location', 'string', 'Created resource URL', 'uri', true);

        $this->assertSame(201, $header->status);
        $this->assertSame('Location', $header->name);
        $this->assertSame('string', $header->type);
        $this->assertSame('Created resource URL', $header->description);
        $this->assertSame('uri', $header->format);
        $this->assertTrue($header->required);
    }

    public function test_api_response_header_defaults(): void
    {
        $header = new ApiResponseHeader(200, 'X-Request-Id');

        $this->assertSame('string', $header->type);
        $this->assertSame('', $header->description);
        $this->assertSame('', $header->format);
        $this->assertFalse($header->required);
    }

    public function test_api_operation_external_docs_defaults_to_null(): void
    {
        $op = new ApiOperation('summary');

        $this->assertNull($op->externalDocsUrl);
        $this->assertSame('', $op->externalDocsDescription);
    }

    public function test_api_tag_external_docs_defaults_to_null(): void
    {
        $tag = new ApiTag('Payments');

        $this->assertNull($tag->externalDocsUrl);
        $this->assertSame('', $tag->externalDocsDescription);
    }

    public static function styleExplodeNullDefaultProvider(): array
    {
        return [
            'ApiQuery'  => [new ApiQuery('filter')],
            'ApiParam'  => [new ApiParam('id')],
            'ApiHeader' => [new ApiHeader('X-Trace')],
        ];
    }

    #[DataProvider('styleExplodeNullDefaultProvider')]
    public function test_style_and_explode_default_to_null(object $attr): void
    {
        $this->assertNull($attr->style);
        $this->assertNull($attr->explode);
    }

    public function test_api_query_style_and_explode_round_trip(): void
    {
        $query = new ApiQuery('ids', style: 'deepObject', explode: true);

        $this->assertSame('deepObject', $query->style);
        $this->assertTrue($query->explode);
    }

    public function test_api_param_style_and_explode_round_trip(): void
    {
        $param = new ApiParam('slug', style: 'label', explode: false);

        $this->assertSame('label', $param->style);
        $this->assertFalse($param->explode);
    }

    public function test_api_header_style_and_explode_round_trip(): void
    {
        $header = new ApiHeader('X-Ids', style: 'simple', explode: true);

        $this->assertSame('simple', $header->style);
        $this->assertTrue($header->explode);
    }
}
