<?php

namespace Botnetdobbs\Luminous\Extractors;

use Botnetdobbs\Luminous\Attributes\ApiShape;
use Botnetdobbs\Luminous\Extractors\Concerns\ExtractsAnnotatedProperties;
use Botnetdobbs\Luminous\Generator\ComponentsRegistry;
use Botnetdobbs\Luminous\Support\Shape;
use Botnetdobbs\Luminous\Support\TypeMapper;
use Illuminate\Validation\Rules\Enum;

class RequestExtractor
{
    use ExtractsAnnotatedProperties;

    private const WILDCARD_ITEMS_KEY = '__items__';

    public function __construct(
        private readonly TypeMapper $typeMapper,
        private readonly ComponentsRegistry $registry,
        private readonly EnumExtractor $enumExtractor,
    ) {}

    public function extract(string $requestClass): array
    {
        if (! class_exists($requestClass)) {
            return ['type' => 'object'];
        }

        if ($this->registry->isRegistered($requestClass)) {
            return ['$ref' => $this->registry->refFor($requestClass)];
        }

        $schema = $this->buildSchema($requestClass);
        $this->registry->register($requestClass, $schema);

        return ['$ref' => $this->registry->refFor($requestClass)];
    }

    /**
     * Determine the request media type.
     * Returns 'multipart/form-data' when any rule contains file/image/mimes keywords.
     */
    public function mediaType(string $requestClass): string
    {
        if (! class_exists($requestClass)) {
            return 'application/json';
        }

        try {
            $instance = (new \ReflectionClass($requestClass))->newInstanceWithoutConstructor();
            if (! method_exists($instance, 'rules')) {
                return 'application/json';
            }

            foreach ($instance->rules() as $fieldRules) {
                $ruleArray = $this->normaliseRules($fieldRules);
                foreach ($ruleArray as $rule) {
                    if (is_string($rule) && in_array($rule, ['file', 'image', 'mimes', 'mimetypes'], true)) {
                        return 'multipart/form-data';
                    }
                    if (is_string($rule) && (str_starts_with($rule, 'mimes:') || str_starts_with($rule, 'mimetypes:'))) {
                        return 'multipart/form-data';
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->debug("Luminous: mediaType() could not call rules() on [{$requestClass}]: {$e->getMessage()}");
        }

        return 'application/json';
    }

    // Internal: schema building

    private function buildSchema(string $requestClass): array
    {
        $reflection = new \ReflectionClass($requestClass);

        // Strategy 1: #[ApiShape] static schema() method
        if (! empty($reflection->getAttributes(ApiShape::class)) && $reflection->hasMethod('schema')) {
            $schemaMethod = $reflection->getMethod('schema');
            if ($schemaMethod->isPublic() && $schemaMethod->isStatic()) {
                try {
                    $shape = $requestClass::schema();
                    if ($shape instanceof Shape) {
                        return $this->resolveShapeEnumRefs($shape->toArray());
                    }
                } catch (\Throwable $e) {
                    logger()->warning("Luminous: schema() on [{$requestClass}] threw: {$e->getMessage()}");
                }
            }
        }

        // Strategy 2: public properties with #[ApiProperty] + hints() overlay
        ['properties' => $properties, 'required' => $required] = $this->extractAnnotatedProperties($reflection, $this->typeMapper, $this->registry, $this->enumExtractor);

        if (! empty($properties)) {
            $hints = $this->loadHints($reflection);
            foreach ($hints as $field => $hint) {
                if (isset($properties[$field])) {
                    $properties[$field] = $this->mergeHint($properties[$field], $hint);
                }
            }

            $schema = ['type' => 'object', 'properties' => $properties];
            if (! empty($required)) {
                $schema['required'] = array_values($required);
            }

            return $schema;
        }

        // Strategy 3: rules() + hints()
        return $this->extractFromRules($reflection);
    }

    // Strategy 3: rules() + hints()

    private function extractFromRules(\ReflectionClass $reflection): array
    {
        $requestClass = $reflection->getName();

        try {
            $instance = $reflection->newInstanceWithoutConstructor();
        } catch (\Throwable $e) {
            logger()->warning("Luminous: Cannot instantiate [{$requestClass}]: {$e->getMessage()}");

            return ['type' => 'object', 'properties' => []];
        }

        if (! method_exists($instance, 'rules')) {
            return ['type' => 'object', 'properties' => []];
        }

        try {
            $allRules = $instance->rules();
        } catch (\Throwable $e) {
            logger()->warning("Luminous: rules() on [{$requestClass}] threw: {$e->getMessage()}");

            return ['type' => 'object', 'properties' => []];
        }

        $hints = $this->loadHints($reflection);
        $properties = [];
        $required = [];

        [$topLevel, $nested, $wildcards] = $this->categoriseRules($allRules);

        foreach ($topLevel as $field => $fieldRules) {
            $ruleArray = $this->normaliseRules($fieldRules);
            $ruleStrings = array_values(array_filter($ruleArray, 'is_string'));

            if (isset($wildcards[$field])) {
                $schema = $this->buildWildcardArraySchema($ruleArray, $wildcards[$field]);
            } elseif (isset($nested[$field])) {
                $schema = $this->buildNestedObjectSchema($nested[$field]);
            } else {
                $schema = $this->typeMapper->validationRulesToSchema($ruleArray);

                // Resolve Rule::enum(). Registers the enum in components and returns a $ref.
                $enumRef = $this->resolveEnumRef($ruleArray);
                if ($enumRef !== null) {
                    $extra = array_intersect_key($schema, array_flip(['description', 'example']));
                    $schema = ! empty($extra)
                        ? array_merge(['allOf' => [['$ref' => $enumRef]]], $extra)
                        : ['$ref' => $enumRef];
                }

                // Handle 'confirmed' rule -> companion field (only if not already declared in rules)
                $confirmField = $field.'_confirmation';
                if (in_array('confirmed', $ruleStrings, true) && ! array_key_exists($confirmField, $topLevel)) {
                    $schema['writeOnly'] = true;
                    if (($schema['type'] ?? '') === 'string' || (is_array($schema['type'] ?? '') && in_array('string', $schema['type'] ?? [], true))) {
                        $schema['format'] = 'password';
                    }
                    $companion = [
                        'type' => $schema['type'] ?? 'string',
                        'writeOnly' => true,
                        'description' => "Must match the {$field} field",
                    ];
                    if (isset($schema['minLength'])) {
                        $companion['minLength'] = $schema['minLength'];
                    }
                    $properties[$confirmField] = $companion;
                    if ($this->isRequired($ruleStrings)) {
                        $required[] = $confirmField;
                    }
                }
            }

            if (isset($hints[$field])) {
                $schema = $this->mergeHint($schema, $hints[$field]);
            }

            $schema = $this->enrichConditionalRequired($schema, $ruleStrings, $field);

            $properties[$field] = $schema;

            if ($this->isRequired($ruleStrings)) {
                $required[] = $field;
            }
        }

        $schema = ['type' => 'object'];
        if (! empty($properties)) {
            $schema['properties'] = $properties;
        }
        if (! empty($required)) {
            $schema['required'] = array_values(array_unique($required));
        }

        return $schema;
    }

    // Rule categorisation

    /**
     * Split rules into three buckets:
     *   - topLevel:  'field' -> rules (plain fields, or parents of dot/wildcard fields)
     *   - nested:    'parent' -> ['child' => rules] (from 'parent.child')
     *   - wildcards: 'parent' -> ['childPath' => rules] (from 'parent.*.child' or 'parent.*')
     *
     * For 'parent.*' (simple scalar wildcard), childPath is '' (empty string).
     */
    private function categoriseRules(array $allRules): array
    {
        $topLevel = [];
        $nested = [];
        $wildcards = [];

        foreach ($allRules as $field => $rules) {
            if (! str_contains((string) $field, '.')) {
                $topLevel[$field] = $rules;

                continue;
            }

            $parts = explode('.', (string) $field, 3);

            // Wildcard: 'items.*.product_id' or 'tag_ids.*'
            if (($parts[1] ?? '') === '*') {
                $parent = $parts[0];
                $childPath = $parts[2] ?? null;

                if ($childPath !== null) {
                    $wildcards[$parent][$childPath] = $rules;
                } else {
                    // Simple wildcard like 'field.*'. Use a named sentinel key so we can tell later
                    // whether the items are scalars or objects, without relying on an empty string key.
                    $wildcards[$parent][self::WILDCARD_ITEMS_KEY] = $rules;
                }

                if (! isset($topLevel[$parent])) {
                    $topLevel[$parent] = [];
                }

                continue;
            }

            // Dot-notation: 'address.street' or deeper
            $parent = $parts[0];
            $child = implode('.', array_slice($parts, 1));
            $nested[$parent][$child] = $rules;

            if (! isset($topLevel[$parent])) {
                $topLevel[$parent] = [];
            }
        }

        return [$topLevel, $nested, $wildcards];
    }

    // Schema builders for complex types

    /**
     * Build a nested object schema from dot-notation child rules.
     * Supports arbitrary nesting depth by recursing when a child also has dots.
     */
    private function buildNestedObjectSchema(array $childRules, int $depth = 0): array
    {
        if ($depth > 15) {
            logger()->warning('Luminous: request schema nesting depth exceeded 15 levels. Schema truncated.');

            return ['type' => 'object'];
        }

        [$topLevel, $nested, $wildcards] = $this->categoriseRules($childRules);

        $properties = [];
        $required = [];

        foreach ($topLevel as $child => $rules) {
            $ruleArray = $this->normaliseRules($rules);
            $ruleStrings = array_values(array_filter($ruleArray, 'is_string'));

            if (isset($wildcards[$child])) {
                $schema = $this->buildWildcardArraySchema($ruleArray, $wildcards[$child], $depth + 1);
            } elseif (isset($nested[$child])) {
                $schema = $this->buildNestedObjectSchema($nested[$child], $depth + 1);
            } else {
                $schema = $this->typeMapper->validationRulesToSchema($ruleArray) ?: ['type' => 'string'];

                // Register Rule::enum() as a component ref, same as top-level fields do.
                $enumRef = $this->resolveEnumRef($ruleArray);
                if ($enumRef !== null) {
                    $extra = array_intersect_key($schema, array_flip(['description', 'example']));
                    $schema = ! empty($extra)
                        ? array_merge(['allOf' => [['$ref' => $enumRef]]], $extra)
                        : ['$ref' => $enumRef];
                }
            }

            $properties[$child] = $schema;

            if ($this->isRequired($ruleStrings)) {
                $required[] = $child;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if (! empty($required)) {
            $schema['required'] = array_values($required);
        }

        return $schema;
    }

    /**
     * Build a typed array schema from wildcard rules.
     *
     * Simple wildcard ('tag_ids.*'): { type: array, items: { type: string, format: uuid } }
     * Object wildcard ('items.*.qty'): { type: array, items: { type: object, properties: {...} } }
     */
    private function buildWildcardArraySchema(array $parentRules, array $childRules, int $depth = 0): array
    {
        $parentSchema = $this->typeMapper->validationRulesToSchema($parentRules);
        $schema = ['type' => 'array'];

        if (isset($parentSchema['minItems'])) {
            $schema['minItems'] = $parentSchema['minItems'];
        }
        if (isset($parentSchema['maxItems'])) {
            $schema['maxItems'] = $parentSchema['maxItems'];
        }

        $isSimple = isset($childRules[self::WILDCARD_ITEMS_KEY]);

        if ($isSimple || empty($childRules)) {
            $itemRuleArray = $this->normaliseRules($childRules[self::WILDCARD_ITEMS_KEY] ?? []);
            $schema['items'] = $this->typeMapper->validationRulesToSchema($itemRuleArray) ?: ['type' => 'string'];
        } else {
            // Object wildcard. Build item schema from child rules (exclude the __items__ sentinel)
            $objectRules = array_diff_key($childRules, [self::WILDCARD_ITEMS_KEY => true]);
            $schema['items'] = $this->buildNestedObjectSchema($objectRules, $depth + 1);
        }

        return $schema;
    }

    // Hints

    private function loadHints(\ReflectionClass $reflection): array
    {
        try {
            $instance = $reflection->newInstanceWithoutConstructor();
            if (! method_exists($instance, 'hints')) {
                return [];
            }
            $hints = $instance->hints();

            return is_array($hints) ? $hints : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Merge hint description and example into schema. Never overrides types or constraints.
     */
    private function mergeHint(array $schema, Shape|array $hint): array
    {
        $hintArray = $hint instanceof Shape ? $hint->toArray() : $hint;

        if (isset($hintArray['description']) && ! isset($schema['description'])) {
            $schema['description'] = $hintArray['description'];
        }
        if (isset($hintArray['example']) && ! isset($schema['example'])) {
            $schema['example'] = $hintArray['example'];
        }

        return $schema;
    }

    // Rule helpers

    private function normaliseRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }
        if (is_array($rules)) {
            $flat = [];
            foreach ($rules as $rule) {
                if (is_string($rule) && str_contains($rule, '|')) {
                    array_push($flat, ...explode('|', $rule));
                } else {
                    $flat[] = $rule;
                }
            }

            return $flat;
        }

        return [];
    }

    private function isRequired(array $ruleStrings): bool
    {
        if (! in_array('required', $ruleStrings, true)) {
            return false;
        }

        return ! in_array('sometimes', $ruleStrings, true);
    }

    private function resolveEnumRef(array $ruleArray): ?string
    {
        foreach ($ruleArray as $rule) {
            if (! is_object($rule) || ! $rule instanceof Enum) {
                continue;
            }
            $class = $this->enumExtractor->classFromRule($rule);
            if ($class !== null) {
                $this->registry->register($class, $this->enumExtractor->extract($class));

                return $this->registry->refFor($class);
            }
        }

        return null;
    }

    private function enrichConditionalRequired(array $schema, array $ruleStrings, string $field): array
    {
        if (isset($schema['description'])) {
            return $schema;
        }

        foreach ($ruleStrings as $rule) {
            if (str_starts_with((string) $rule, 'required_if:')) {
                [, $condition] = explode(':', $rule, 2);
                [$otherField, $value] = explode(',', $condition, 2);
                $schema['description'] = "Required when {$otherField} is '{$value}'.";

                return $schema;
            }
            if (str_starts_with((string) $rule, 'required_unless:')) {
                [, $condition] = explode(':', $rule, 2);
                [$otherField, $value] = explode(',', $condition, 2);
                $schema['description'] = "Required unless {$otherField} is '{$value}'.";

                return $schema;
            }
            if (str_starts_with((string) $rule, 'required_with:')) {
                [, $fields] = explode(':', $rule, 2);
                $schema['description'] = "Required when any of [{$fields}] is present.";

                return $schema;
            }
            if (str_starts_with((string) $rule, 'required_without:')) {
                [, $fields] = explode(':', $rule, 2);
                $schema['description'] = "Required when none of [{$fields}] is present.";

                return $schema;
            }
        }

        return $schema;
    }

    /**
     * Recursively resolve BackedEnum class-name refs in a Shape-produced schema.
     * Resource class refs are NOT resolved here. FormRequests don't reference resources.
     */
    private function resolveShapeEnumRefs(array $schema): array
    {
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];
            if (class_exists($ref) && is_subclass_of($ref, \BackedEnum::class)) {
                $schema['$ref'] = self::registerEnumRef($ref, $this->registry, $this->enumExtractor);
            }

            return $schema;
        }

        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $schema[$key] = $this->resolveShapeEnumRefs($value);
            }
        }

        return $schema;
    }
}
