<?php

namespace Botnetdobbs\Luminous\Tests\Unit;

use Botnetdobbs\Luminous\Support\TypeMapper;
use Botnetdobbs\Luminous\Tests\Fixtures\Enums\PaymentStatus;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\TestCase;

class TypeMapperTest extends TestCase
{
    private TypeMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new TypeMapper;
    }

    public function test_integer_min_max_produces_minimum_maximum_not_length(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['required', 'integer', 'min:1', 'max:1000000']);

        $this->assertSame('integer', $schema['type']);
        $this->assertSame(1, $schema['minimum']);
        $this->assertSame(1000000, $schema['maximum']);
        $this->assertArrayNotHasKey('minLength', $schema);
        $this->assertArrayNotHasKey('maxLength', $schema);
    }

    public function test_string_min_max_produces_length_not_numeric(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['required', 'string', 'min:3', 'max:255']);

        $this->assertSame('string', $schema['type']);
        $this->assertSame(3, $schema['minLength']);
        $this->assertSame(255, $schema['maxLength']);
        $this->assertArrayNotHasKey('minimum', $schema);
        $this->assertArrayNotHasKey('maximum', $schema);
    }

    public function test_array_min_max_produces_min_max_items(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['array', 'min:1', 'max:10']);

        $this->assertSame('array', $schema['type']);
        $this->assertSame(1, $schema['minItems']);
        $this->assertSame(10, $schema['maxItems']);
        $this->assertArrayNotHasKey('minLength', $schema);
        $this->assertArrayNotHasKey('minimum', $schema);
    }

    public function test_in_rule_produces_enum(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'in:a,b,c']);

        $this->assertSame(['a', 'b', 'c'], $schema['enum']);
    }

    public function test_nullable_produces_openapi31_type_array(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'nullable']);

        $this->assertSame(['string', 'null'], $schema['type']);
        $this->assertArrayNotHasKey('nullable', $schema);
    }

    public function test_nullable_on_integer_produces_type_array(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['integer', 'nullable']);

        $this->assertSame(['integer', 'null'], $schema['type']);
        $this->assertArrayNotHasKey('nullable', $schema);
    }

    public function test_file_rule_produces_binary_format(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['file']);

        $this->assertSame('string', $schema['type']);
        $this->assertSame('binary', $schema['format']);
    }

    public function test_uuid_rule_produces_uuid_format(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'uuid']);

        $this->assertSame('uuid', $schema['format']);
    }

    public function test_email_rule_produces_email_format(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'email']);

        $this->assertSame('email', $schema['format']);
    }

    public function test_between_on_integer_produces_numeric_range(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['integer', 'between:1,100']);

        $this->assertSame(1, $schema['minimum']);
        $this->assertSame(100, $schema['maximum']);
        $this->assertArrayNotHasKey('minLength', $schema);
    }

    public function test_between_on_string_produces_length_range(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'between:1,100']);

        $this->assertSame(1, $schema['minLength']);
        $this->assertSame(100, $schema['maxLength']);
        $this->assertArrayNotHasKey('minimum', $schema);
    }

    public function test_digits_on_string_produces_length_constraints(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'digits:5']);

        $this->assertSame(5, $schema['minLength']);
        $this->assertSame(5, $schema['maxLength']);
    }

    public function test_digits_on_integer_produces_no_length_constraints(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['integer', 'digits:5']);

        $this->assertArrayNotHasKey('minLength', $schema);
        $this->assertArrayNotHasKey('maxLength', $schema);
    }

    public function test_backed_enum_class_produces_correct_schema(): void
    {
        $schema = $this->mapper->phpTypeToOpenApi(PaymentStatus::class);

        $this->assertSame('string', $schema['type']);
        $this->assertContains('succeeded', $schema['enum']);
        $this->assertContains('initiated', $schema['enum']);
        $this->assertCount(5, $schema['enum']);
    }

    public function test_php_type_format_takes_priority_over_type(): void
    {
        $schema = $this->mapper->phpTypeToOpenApi('string', 'uuid');

        $this->assertSame('string', $schema['type']);
        $this->assertSame('uuid', $schema['format']);
    }

    public function test_enum_to_open_api_extracts_all_case_values(): void
    {
        $schema = $this->mapper->enumToOpenApi(PaymentStatus::class);

        $this->assertSame('string', $schema['type']);
        $this->assertSame(
            ['initiated', 'processing', 'succeeded', 'failed', 'timeout_pending'],
            $schema['enum']
        );
    }

    public function test_rule_enum_object_extracts_enum_values(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', Rule::enum(PaymentStatus::class)]);

        $this->assertArrayHasKey('enum', $schema);
        $this->assertContains('succeeded', $schema['enum']);
    }

    public function test_nullable_union_type_resolves_base_type(): void
    {
        $schema = $this->mapper->phpTypeToOpenApi('?int');

        $this->assertSame('integer', $schema['type']);
    }

    public function test_union_type_with_null_resolves_non_null_part(): void
    {
        $schema = $this->mapper->phpTypeToOpenApi('string|null');

        $this->assertSame('string', $schema['type']);
    }

    public function test_digits_rule_also_produces_pattern(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'digits:6']);

        $this->assertSame(6, $schema['minLength']);
        $this->assertSame(6, $schema['maxLength']);
        $this->assertSame('^\\d{6}$', $schema['pattern']);
    }

    public function test_regex_rule_strips_delimiter(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'regex:/^[A-Z]+$/']);

        $this->assertSame('^[A-Z]+$', $schema['pattern']);
    }

    public function test_regex_rule_without_delimiter_passes_through(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'regex:^[A-Z]+$']);

        $this->assertSame('^[A-Z]+$', $schema['pattern']);
    }

    public function test_ip_rule_produces_ipv4_format(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'ip']);

        $this->assertSame('ipv4', $schema['format']);
    }

    public function test_ipv4_rule_produces_ipv4_format(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'ipv4']);

        $this->assertSame('ipv4', $schema['format']);
    }

    public function test_ipv6_rule_produces_ipv6_format(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'ipv6']);

        $this->assertSame('ipv6', $schema['format']);
    }

    public function test_decimal_rule_produces_number_type(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['decimal']);

        $this->assertSame('number', $schema['type']);
    }

    public function test_confirmed_rule_sets_write_only_and_password_format(): void
    {
        $schema = $this->mapper->validationRulesToSchema(['string', 'confirmed']);

        $this->assertTrue($schema['writeOnly']);
        $this->assertSame('password', $schema['format']);
    }
}
