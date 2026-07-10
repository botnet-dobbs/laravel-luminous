<?php

namespace Botnetdobbs\Luminous\Tests\Unit;

use Botnetdobbs\Luminous\Support\Shape;
use Botnetdobbs\Luminous\Tests\Fixtures\Enums\PaymentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShapeTest extends TestCase
{
    public static function primitiveTypeProvider(): array
    {
        return [
            'string'  => [Shape::string(),  ['type' => 'string']],
            'integer' => [Shape::integer(), ['type' => 'integer']],
            'boolean' => [Shape::boolean(), ['type' => 'boolean']],
        ];
    }

    #[DataProvider('primitiveTypeProvider')]
    public function test_primitive_type_produces_correct_schema(Shape $shape, array $expected): void
    {
        $this->assertSame($expected, $shape->toArray());
    }

    public function test_format_shortcuts_produce_correct_schemas(): void
    {
        $this->assertSame(['type' => 'string', 'format' => 'uuid'], Shape::uuid()->toArray());
        $this->assertSame(['type' => 'string', 'format' => 'email'], Shape::email()->toArray());
        $this->assertSame(['type' => 'string', 'format' => 'date-time'], Shape::dateTime()->toArray());
        $this->assertSame(['type' => 'string', 'format' => 'date'], Shape::date()->toArray());
        $this->assertSame(['type' => 'string', 'format' => 'time'], Shape::time()->toArray());
        $this->assertSame(['type' => 'string', 'format' => 'uri'], Shape::url()->toArray());
    }

    public function test_nullable_on_string_produces_openapi31_type_array(): void
    {
        $schema = Shape::string()->nullable()->toArray();

        $this->assertSame(['string', 'null'], $schema['type']);
        $this->assertArrayNotHasKey('nullable', $schema);
        $this->assertArrayNotHasKey('x-nullable', $schema);
    }

    public function test_nullable_on_uuid_preserves_format(): void
    {
        $schema = Shape::uuid()->nullable()->toArray();

        $this->assertSame(['string', 'null'], $schema['type']);
        $this->assertSame('uuid', $schema['format']);
    }

    public function test_nullable_on_ref_produces_one_of(): void
    {
        $schema = Shape::ref('SomeClass')->nullable()->toArray();

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertContains(['$ref' => 'SomeClass'], $schema['oneOf']);
        $this->assertContains(['type' => 'null'], $schema['oneOf']);
        $this->assertArrayNotHasKey('$ref', $schema);
        $this->assertArrayNotHasKey('nullable', $schema);
    }

    public function test_nullable_on_all_of_wraps_in_one_of(): void
    {
        $schema = Shape::allOf([Shape::string(), Shape::integer()])->nullable()->toArray();

        // allOf + nullable must wrap in oneOf, not append {type:null} to allOf members
        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertArrayNotHasKey('allOf', $schema);
        $this->assertContains(['type' => 'null'], $schema['oneOf']);
        // The allOf schema itself is the first oneOf member
        $this->assertArrayHasKey('allOf', $schema['oneOf'][0]);
    }

    public function test_nullable_on_one_of_appends_null_member(): void
    {
        $schema = Shape::oneOf([Shape::string(), Shape::integer()])->nullable()->toArray();

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertContains(['type' => 'null'], $schema['oneOf']);
        $this->assertCount(3, $schema['oneOf']);
    }

    public function test_optional_removes_field_from_required_in_object(): void
    {
        $schema = Shape::object([
            'name' => Shape::string(),
            'note' => Shape::string()->optional(),
        ])->toArray();

        $this->assertContains('name', $schema['required']);
        $this->assertNotContains('note', $schema['required']);
    }

    public function test_nullable_does_not_exclude_field_from_required(): void
    {
        // Nullable means the value can be null, but the key must still be present.
        // Only optional() removes a field from required.
        $schema = Shape::object([
            'name' => Shape::string(),
            'settled_at' => Shape::dateTime()->nullable(),
        ])->toArray();

        $this->assertContains('name', $schema['required']);
        $this->assertContains('settled_at', $schema['required']);
        $this->assertSame(['string', 'null'], $schema['properties']['settled_at']['type']);
    }

    public function test_only_optional_excludes_field_from_required(): void
    {
        $schema = Shape::object([
            'required_field' => Shape::string(),
            'optional_field' => Shape::string()->optional(),
            'nullable_required' => Shape::string()->nullable(),
            'optional_and_nullable' => Shape::string()->nullable()->optional(),
        ])->toArray();

        $this->assertContains('required_field', $schema['required']);
        $this->assertNotContains('optional_field', $schema['required']);
        $this->assertContains('nullable_required', $schema['required']);
        $this->assertNotContains('optional_and_nullable', $schema['required']);
    }

    public static function booleanModifierProvider(): array
    {
        return [
            'readOnly'   => [Shape::string()->readOnly(),   'readOnly'],
            'writeOnly'  => [Shape::string()->writeOnly(),  'writeOnly'],
            'deprecated' => [Shape::string()->deprecated(), 'deprecated'],
        ];
    }

    #[DataProvider('booleanModifierProvider')]
    public function test_boolean_modifier_sets_flag(Shape $shape, string $key): void
    {
        $this->assertTrue($shape->toArray()[$key]);
    }

    public function test_description_and_example_modifiers(): void
    {
        $schema = Shape::integer()->description('The amount')->example(100)->toArray();

        $this->assertSame('The amount', $schema['description']);
        $this->assertSame(100, $schema['example']);
    }

    public function test_min_max_on_integer_produce_minimum_maximum(): void
    {
        $schema = Shape::integer()->min(1)->max(100)->toArray();

        $this->assertSame(1, $schema['minimum']);
        $this->assertSame(100, $schema['maximum']);
        $this->assertArrayNotHasKey('minLength', $schema);
    }

    public function test_min_max_on_string_produce_length_constraints(): void
    {
        $schema = Shape::string()->min(3)->max(255)->toArray();

        $this->assertSame(3, $schema['minLength']);
        $this->assertSame(255, $schema['maxLength']);
        $this->assertArrayNotHasKey('minimum', $schema);
    }

    public function test_min_max_on_array_produce_items_constraints(): void
    {
        $schema = Shape::array()->min(1)->max(10)->toArray();

        $this->assertSame(1, $schema['minItems']);
        $this->assertSame(10, $schema['maxItems']);
    }

    public function test_array_of_builds_typed_array(): void
    {
        $schema = Shape::arrayOf(Shape::uuid())->toArray();

        $this->assertSame('array', $schema['type']);
        $this->assertSame(['type' => 'string', 'format' => 'uuid'], $schema['items']);
    }

    public function test_object_builds_properties_and_required(): void
    {
        $schema = Shape::object([
            'id' => Shape::uuid(),
            'name' => Shape::string(),
        ])->toArray();

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertSame(['id', 'name'], $schema['required']);
    }

    public function test_enum_factory_stores_class_name_as_ref(): void
    {
        $schema = Shape::enum(PaymentStatus::class)->toArray();

        // Class name stored as-is; extractor resolves to component ref
        $this->assertSame(PaymentStatus::class, $schema['$ref']);
    }

    public function test_ref_factory_stores_class_name_as_ref(): void
    {
        $schema = Shape::ref('App\\Http\\Resources\\PaymentResource')->toArray();

        $this->assertSame('App\\Http\\Resources\\PaymentResource', $schema['$ref']);
    }

    public function test_internal_markers_stripped_from_output(): void
    {
        $schema = Shape::string()->nullable()->optional()->toArray();

        $this->assertArrayNotHasKey('x-nullable', $schema);
        $this->assertArrayNotHasKey('x-optional', $schema);
    }

    public function test_is_optional_and_is_nullable_read_internal_markers(): void
    {
        $shape = Shape::string()->nullable()->optional();

        $this->assertTrue($shape->isNullable());
        $this->assertTrue($shape->isOptional());
        $this->assertFalse(Shape::string()->isNullable());
        $this->assertFalse(Shape::string()->isOptional());
    }

    public function test_shape_is_immutable(): void
    {
        $original = Shape::string();
        $nullable = $original->nullable();

        $this->assertFalse($original->isNullable());
        $this->assertTrue($nullable->isNullable());
    }
}
