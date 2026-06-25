<?php

namespace Botnetdobbs\Luminous\Tests\Unit;

use Botnetdobbs\Luminous\Attributes\ApiBody;
use Botnetdobbs\Luminous\Attributes\ApiComposedOf;
use Botnetdobbs\Luminous\Attributes\ApiHeader;
use Botnetdobbs\Luminous\Attributes\ApiIgnore;
use Botnetdobbs\Luminous\Attributes\ApiItems;
use Botnetdobbs\Luminous\Attributes\ApiNoSecurity;
use Botnetdobbs\Luminous\Attributes\ApiOperation;
use Botnetdobbs\Luminous\Attributes\ApiParam;
use Botnetdobbs\Luminous\Attributes\ApiProperty;
use Botnetdobbs\Luminous\Attributes\ApiQuery;
use Botnetdobbs\Luminous\Attributes\ApiResponse;
use Botnetdobbs\Luminous\Attributes\ApiSecurity;
use Botnetdobbs\Luminous\Attributes\ApiTag;
use Botnetdobbs\Luminous\Tests\Fixtures\Controllers\TestAttributeController;
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

    public function test_api_param_constructor_round_trip(): void
    {
        $param = new ApiParam('id', 'Resource UUID', 'string', 'uuid', true, '550e8400');

        $this->assertSame('id', $param->name);
        $this->assertSame('string', $param->type);
        $this->assertSame('uuid', $param->format);
        $this->assertTrue($param->required);
        $this->assertSame('550e8400', $param->example);
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
}
