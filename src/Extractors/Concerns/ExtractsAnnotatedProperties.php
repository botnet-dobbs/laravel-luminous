<?php

namespace Botnetdobbs\Luminous\Extractors\Concerns;

use Botnetdobbs\Luminous\Attributes\ApiIgnore;
use Botnetdobbs\Luminous\Attributes\ApiItems;
use Botnetdobbs\Luminous\Attributes\ApiProperty;
use Botnetdobbs\Luminous\Extractors\EnumExtractor;
use Botnetdobbs\Luminous\Generator\ComponentsRegistry;
use Botnetdobbs\Luminous\Support\TypeMapper;

trait ExtractsAnnotatedProperties
{
    protected function extractAnnotatedProperties(
        \ReflectionClass $reflection,
        TypeMapper $typeMapper,
        ComponentsRegistry $registry,
        EnumExtractor $enumExtractor,
    ): array {
        $properties = [];
        $required = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $attrs = $prop->getAttributes(ApiProperty::class);
            if (empty($attrs)) {
                continue;
            }
            if (! empty($prop->getAttributes(ApiIgnore::class))) {
                continue;
            }

            /** @var ApiProperty $apiProp */
            $apiProp = $attrs[0]->newInstance();
            $reflType = $prop->getType();
            $phpType = $reflType instanceof \ReflectionNamedType ? $reflType->getName() : 'mixed';
            $nullable = ($reflType?->allowsNull() ?? false) || $apiProp->nullable;
            $propName = $prop->getName();

            // Explicit $ref. OpenAPI 3.2 nullable refs use oneOf.
            if ($apiProp->ref) {
                if ($nullable) {
                    $properties[$propName] = ['oneOf' => [['$ref' => $apiProp->ref], ['type' => 'null']]];
                } else {
                    $properties[$propName] = ['$ref' => $apiProp->ref];
                }
                // nullable means value can be null but key is still required
                // only optional: true removes from required
                if (! $apiProp->optional) {
                    $required[] = $propName;
                }

                continue;
            }

            // PHP backed enum. Register in components and reference it. Nullable uses oneOf.
            if (class_exists($phpType) && is_subclass_of($phpType, \BackedEnum::class)) {
                $ref = self::registerEnumRef($phpType, $registry, $enumExtractor);
                if ($nullable) {
                    $properties[$propName] = ['oneOf' => [['$ref' => $ref], ['type' => 'null']]];
                } else {
                    $properties[$propName] = ['$ref' => $ref];
                }
                if (! $apiProp->optional) {
                    $required[] = $propName;
                }

                continue;
            }

            $schema = $typeMapper->phpTypeToOpenApi($phpType, $apiProp->format);

            if ($apiProp->description) {
                $schema['description'] = $apiProp->description;
            }
            if ($apiProp->example !== null) {
                $schema['example'] = $apiProp->example;
            }
            // OpenAPI 3.2: nullable primitives use type array instead of nullable: true.
            // Guard empty schema (e.g. int|float union). Applying nullable to {} wrongly yields {type:[string,null]}.
            if ($nullable && ! empty($schema)) {
                $schema = TypeMapper::applyNullable($schema);
            }
            if ($apiProp->minimum !== null) {
                $schema['minimum'] = $apiProp->minimum;
            }
            if ($apiProp->maximum !== null) {
                $schema['maximum'] = $apiProp->maximum;
            }
            if ($apiProp->minLength !== null) {
                $schema['minLength'] = $apiProp->minLength;
            }
            if ($apiProp->maxLength !== null) {
                $schema['maxLength'] = $apiProp->maxLength;
            }
            if (! empty($apiProp->enum)) {
                $schema['enum'] = $apiProp->enum;
            }
            if ($apiProp->readOnly) {
                $schema['readOnly'] = true;
            }
            if ($apiProp->writeOnly) {
                $schema['writeOnly'] = true;
            }
            if ($apiProp->deprecated) {
                $schema['deprecated'] = true;
            }
            if ($apiProp->pattern) {
                $schema['pattern'] = $apiProp->pattern;
            }

            if (($schema['type'] ?? '') === 'array' || (is_array($schema['type'] ?? '') && in_array('array', $schema['type'] ?? [], true))) {
                $schema['items'] = $this->resolveArrayItems($prop, $apiProp);
            }

            $properties[$propName] = $schema;
            // Only optional: true removes from required. nullable only changes the type, not presence
            if (! $apiProp->optional) {
                $required[] = $propName;
            }
        }

        return compact('properties', 'required');
    }

    private static function registerEnumRef(
        string $class,
        ComponentsRegistry $registry,
        EnumExtractor $enumExtractor,
    ): string {
        $registry->register($class, $enumExtractor->extract($class));

        return $registry->refFor($class);
    }

    private function resolveArrayItems(\ReflectionProperty $prop, ApiProperty $apiProp): array
    {
        $itemsAttr = $prop->getAttributes(ApiItems::class);
        if (! empty($itemsAttr)) {
            $items = $itemsAttr[0]->newInstance();
            if ($items->ref) {
                return ['$ref' => $items->ref];
            }
            if ($items->type) {
                return collect(['type' => $items->type, 'format' => $items->format ?: null, 'enum' => $items->enum ?: null])
                    ->filter(fn ($v) => $v !== null && $v !== [])
                    ->all();
            }
        }

        if ($apiProp->itemsRef) {
            return ['$ref' => $apiProp->itemsRef];
        }

        if ($apiProp->itemsType) {
            return ['type' => $apiProp->itemsType];
        }

        return ['type' => 'string'];
    }
}
