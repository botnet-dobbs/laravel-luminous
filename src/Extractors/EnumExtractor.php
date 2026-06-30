<?php

namespace Botnetdobbs\Luminous\Extractors;

class EnumExtractor
{
    public function classFromRule(object $rule): ?string
    {
        try {
            foreach ((new \ReflectionObject($rule))->getProperties() as $prop) {
                $val = $prop->getValue($rule);
                if (is_string($val) && class_exists($val) && is_subclass_of($val, \BackedEnum::class)) {
                    return $val;
                }
            }
        } catch (\Throwable) {
            // Rule::enum() internals may change between framework versions
        }

        return null;
    }

    public function extract(string $enumClass): array
    {
        if (! class_exists($enumClass) || ! is_subclass_of($enumClass, \BackedEnum::class)) {
            return ['type' => 'string'];
        }

        $reflection = new \ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType()?->getName() ?? 'string';
        $cases = collect($enumClass::cases())->map(fn ($c) => $c->value)->all();

        $schema = [
            'type' => $backingType === 'int' ? 'integer' : 'string',
            'enum' => $cases,
        ];

        $descriptions = collect($reflection->getCases())
            ->mapWithKeys(function ($case) {
                $doc = $case->getDocComment();
                if (! $doc) {
                    return [];
                }
                if (preg_match('/@description\s+(.+)/u', $doc, $m)) {
                    return [$case->getValue() => trim($m[1])];
                }
                if (preg_match('/\*\s+([^@\*][^\n]+)/u', $doc, $m)) {
                    return [$case->getValue() => trim($m[1])];
                }

                return [];
            })
            ->filter()
            ->all();

        if (! empty($descriptions)) {
            $schema['x-enum-descriptions'] = $descriptions;
        }

        return $schema;
    }
}
