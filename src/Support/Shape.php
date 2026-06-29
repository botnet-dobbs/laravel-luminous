<?php

namespace Botnetdobbs\Luminous\Support;

final class Shape
{
    private function __construct(private array $schema) {}

    // Primitive factories

    public static function string(): self
    {
        return new self(['type' => 'string']);
    }

    public static function integer(): self
    {
        return new self(['type' => 'integer']);
    }

    public static function number(): self
    {
        return new self(['type' => 'number']);
    }

    public static function boolean(): self
    {
        return new self(['type' => 'boolean']);
    }

    public static function array(): self
    {
        return new self(['type' => 'array']);
    }

    // Format shortcuts

    public static function uuid(): self
    {
        return new self(['type' => 'string', 'format' => 'uuid']);
    }

    public static function email(): self
    {
        return new self(['type' => 'string', 'format' => 'email']);
    }

    public static function url(): self
    {
        return new self(['type' => 'string', 'format' => 'uri']);
    }

    public static function dateTime(): self
    {
        return new self(['type' => 'string', 'format' => 'date-time']);
    }

    public static function date(): self
    {
        return new self(['type' => 'string', 'format' => 'date']);
    }

    public static function time(): self
    {
        return new self(['type' => 'string', 'format' => 'time']);
    }

    public static function password(): self
    {
        return new self(['type' => 'string', 'format' => 'password']);
    }

    public static function binary(): self
    {
        return new self(['type' => 'string', 'format' => 'binary']);
    }

    // Composite factories

    public static function object(array $properties): self
    {
        $builtProps = [];
        $required = [];

        foreach ($properties as $name => $shape) {
            if ($shape instanceof self) {
                // nullable means value can be null but the key must still be present (required)
                // optional means the key may be absent entirely (not required)
                $skip = $shape->isOptional();
                $built = $shape->toArray();
            } else {
                $built = $shape;
                $skip = $built['x-optional'] ?? false;
                $built = array_diff_key($built, ['x-optional' => true, 'x-nullable' => true]);
            }

            $builtProps[$name] = $built;

            if (! $skip) {
                $required[] = $name;
            }
        }

        $schema = ['type' => 'object', 'properties' => $builtProps];
        if (! empty($required)) {
            $schema['required'] = $required;
        }

        return new self($schema);
    }

    public static function arrayOf(self $items): self
    {
        return new self(['type' => 'array', 'items' => $items->toArray()]);
    }

    public static function ref(string $classOrRef): self
    {
        return new self(['$ref' => $classOrRef]);
    }

    /**
     * Reference a PHP backed enum class. The extractor resolves the class
     * name to a registered component $ref. Do not pass raw #/components/... strings here.
     */
    public static function enum(string $enumClass): self
    {
        return new self(['$ref' => $enumClass]);
    }

    public static function oneOf(array $shapes): self
    {
        return new self(['oneOf' => array_map(fn ($s) => $s instanceof self ? $s->toArray() : $s, $shapes)]);
    }

    public static function anyOf(array $shapes): self
    {
        return new self(['anyOf' => array_map(fn ($s) => $s instanceof self ? $s->toArray() : $s, $shapes)]);
    }

    public static function allOf(array $shapes): self
    {
        return new self(['allOf' => array_map(fn ($s) => $s instanceof self ? $s->toArray() : $s, $shapes)]);
    }

    // Chainable modifiers. All return a clone (immutable)

    public function nullable(): self
    {
        $clone = clone $this;
        $clone->schema['x-nullable'] = true;

        return $clone;
    }

    public function optional(): self
    {
        $clone = clone $this;
        $clone->schema['x-optional'] = true;

        return $clone;
    }

    public function readOnly(): self
    {
        $clone = clone $this;
        $clone->schema['readOnly'] = true;

        return $clone;
    }

    public function writeOnly(): self
    {
        $clone = clone $this;
        $clone->schema['writeOnly'] = true;

        return $clone;
    }

    public function deprecated(): self
    {
        $clone = clone $this;
        $clone->schema['deprecated'] = true;

        return $clone;
    }

    public function description(string $description): self
    {
        $clone = clone $this;
        $clone->schema['description'] = $description;

        return $clone;
    }

    public function example(mixed $example): self
    {
        $clone = clone $this;
        $clone->schema['example'] = $example;

        return $clone;
    }

    /**
     * Set min constraint. Infers minimum/minLength/minItems from the current type.
     */
    public function min(int $value): self
    {
        $clone = clone $this;
        $type = $clone->schema['type'] ?? 'string';

        if (in_array($type, ['integer', 'number'], true)) {
            $clone->schema['minimum'] = $value;
        } elseif ($type === 'array') {
            $clone->schema['minItems'] = $value;
        } else {
            $clone->schema['minLength'] = $value;
        }

        return $clone;
    }

    /**
     * Set max constraint. Infers maximum/maxLength/maxItems from the current type.
     */
    public function max(int $value): self
    {
        $clone = clone $this;
        $type = $clone->schema['type'] ?? 'string';

        if (in_array($type, ['integer', 'number'], true)) {
            $clone->schema['maximum'] = $value;
        } elseif ($type === 'array') {
            $clone->schema['maxItems'] = $value;
        } else {
            $clone->schema['maxLength'] = $value;
        }

        return $clone;
    }

    public function minLength(int $value): self
    {
        $clone = clone $this;
        $clone->schema['minLength'] = $value;

        return $clone;
    }

    public function maxLength(int $value): self
    {
        $clone = clone $this;
        $clone->schema['maxLength'] = $value;

        return $clone;
    }

    public function minItems(int $value): self
    {
        $clone = clone $this;
        $clone->schema['minItems'] = $value;

        return $clone;
    }

    public function maxItems(int $value): self
    {
        $clone = clone $this;
        $clone->schema['maxItems'] = $value;

        return $clone;
    }

    public function pattern(string $pattern): self
    {
        $clone = clone $this;
        $clone->schema['pattern'] = $pattern;

        return $clone;
    }

    /**
     * Set literal enum values (string/int list). Use Shape::enum(Class::class) for PHP backed enums.
     */
    public function values(array $values): self
    {
        $clone = clone $this;
        $clone->schema['enum'] = $values;

        return $clone;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inspection helpers. Read internal markers before toArray() strips them.
    // ─────────────────────────────────────────────────────────────────────────

    public function isOptional(): bool
    {
        return $this->schema['x-optional'] ?? false;
    }

    public function isNullable(): bool
    {
        return $this->schema['x-nullable'] ?? false;
    }

    // Output

    public function toArray(): array
    {
        $schema = $this->schema;
        $isNullable = $schema['x-nullable'] ?? false;
        unset($schema['x-optional'], $schema['x-nullable']);

        if (! $isNullable) {
            return $schema;
        }

        // Nullable $ref: wrap in oneOf with null type
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];
            $extras = array_diff_key($schema, ['$ref' => true]);
            $result = ['oneOf' => [['$ref' => $ref], ['type' => 'null']]];

            return $extras ? array_merge($result, $extras) : $result;
        }

        // Nullable oneOf/anyOf: adding {type:null} as a member is fine because the value just needs to match any one of them.
        foreach (['oneOf', 'anyOf'] as $key) {
            if (isset($schema[$key])) {
                $schema[$key][] = ['type' => 'null'];

                return $schema;
            }
        }

        // Nullable allOf: appending {type:null} is unsatisfiable (all members must match simultaneously).
        // Wrap the entire allOf schema in oneOf instead.
        if (isset($schema['allOf'])) {
            return ['oneOf' => [$schema, ['type' => 'null']]];
        }

        // Nullable primitive: type array
        if (isset($schema['type']) && is_string($schema['type'])) {
            $schema['type'] = [$schema['type'], 'null'];
        }

        return $schema;
    }
}
