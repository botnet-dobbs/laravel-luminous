<?php

namespace Botnetdobbs\Luminous\Extractors;

use Botnetdobbs\Luminous\Attributes\ApiShape;
use Botnetdobbs\Luminous\Extractors\Concerns\ExtractsAnnotatedProperties;
use Botnetdobbs\Luminous\Generator\ComponentsRegistry;
use Botnetdobbs\Luminous\Support\Shape;
use Botnetdobbs\Luminous\Support\TypeMapper;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceExtractor
{
    use ExtractsAnnotatedProperties;

    public function __construct(
        private readonly TypeMapper $typeMapperInstance,
        private readonly ComponentsRegistry $registryInstance,
        private readonly EnumExtractor $enumExtractorInstance,
    ) {}

    protected function typeMapper(): TypeMapper
    {
        return $this->typeMapperInstance;
    }

    protected function registry(): ComponentsRegistry
    {
        return $this->registryInstance;
    }

    protected function enumExtractor(): EnumExtractor
    {
        return $this->enumExtractorInstance;
    }

    public function extract(string $resourceClass): array
    {
        if (! class_exists($resourceClass)) {
            return ['type' => 'object'];
        }

        if ($this->registryInstance->isRegistered($resourceClass)) {
            return ['$ref' => $this->registryInstance->refFor($resourceClass)];
        }

        // Pre-register a placeholder before building so that any recursive call
        // for this class hits isRegistered()=true above and breaks the cycle.
        $ref = $this->registryInstance->register($resourceClass, ['type' => 'object']);

        $schema = $this->buildSchema($resourceClass);

        $this->registryInstance->updateSchema($resourceClass, $schema);

        return ['$ref' => $ref];
    }

    private function buildSchema(string $resourceClass): array
    {
        $reflection = new \ReflectionClass($resourceClass);

        // Strategy 1: #[ApiShape] static schema() method
        if (! empty($reflection->getAttributes(ApiShape::class)) && $reflection->hasMethod('schema')) {
            try {
                $shape = $resourceClass::schema();
                if ($shape instanceof Shape) {
                    return $this->resolveShapeRefs($shape->toArray());
                }
            } catch (\Throwable $e) {
                logger()->warning("Luminous: schema() on [{$resourceClass}] threw: {$e->getMessage()}");
            }
        }

        // Strategy 2: public properties with #[ApiProperty]
        ['properties' => $properties, 'required' => $required] = $this->extractAnnotatedProperties($reflection);

        // Secondary loop: auto-document public properties typed as JsonResource subclasses
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $reflType = $prop->getType();
            $phpType = $reflType instanceof \ReflectionNamedType ? $reflType->getName() : '';
            $isNullable = $reflType?->allowsNull() ?? false;

            if ($phpType && class_exists($phpType) && is_subclass_of($phpType, JsonResource::class)) {
                if (! isset($properties[$prop->getName()])) {
                    $extracted = $this->extract($phpType);
                    $properties[$prop->getName()] = $isNullable
                        ? ['oneOf' => [$extracted, ['type' => 'null']]]
                        : $extracted;

                    if (! $isNullable) {
                        $required[] = $prop->getName();
                    }
                }
            }
        }

        if (empty($properties)) {
            logger()->warning(
                "Luminous: [{$resourceClass}] has no #[ApiShape] schema() and no public #[ApiProperty] properties. ".
                'Schema will be {type: object}. Add a static schema(): Shape method to document the response shape.'
            );

            return ['type' => 'object'];
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if (! empty($required)) {
            $schema['required'] = array_values($required);
        }

        return $schema;
    }

    /**
     * Walk a Shape-produced array and resolve any class-name $ref values
     * into registered component refs, recursively extracting those resources.
     *
     * Shape::ref(OrderItemResource::class) produces { '$ref': 'App\...\OrderItemResource' }.
     * This turns it into { '$ref': '#/components/schemas/OrderItemResource' } and
     * triggers extraction of OrderItemResource if not already done.
     */
    private function resolveShapeRefs(array $schema): array
    {
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];

            if (class_exists($ref)) {
                if (is_subclass_of($ref, \BackedEnum::class)) {
                    $enumSchema = $this->enumExtractorInstance->extract($ref);
                    $enumRef = $this->registryInstance->register($ref, $enumSchema);
                    $schema['$ref'] = $enumRef;

                    return $schema;
                }

                $extracted = $this->extract($ref);
                $schema['$ref'] = $extracted['$ref'] ?? $ref;

                $extras = array_diff_key($schema, ['$ref' => true]);
                if (! empty($extras)) {
                    return array_merge(['$ref' => $schema['$ref']], $extras);
                }

                return ['$ref' => $schema['$ref']];
            }

            // Raw #/components/... paths pass through; anything else is likely a typo
            if (! str_starts_with($ref, '#/')) {
                logger()->warning("Luminous: could not resolve \$ref '{$ref}' in Shape because the class does not exist. Check for typos. Replaced with {type: object}.");

                return ['type' => 'object'];
            }

            return $schema;
        }

        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $schema[$key] = $this->resolveShapeRefs($value);
            }
        }

        return $schema;
    }
}
