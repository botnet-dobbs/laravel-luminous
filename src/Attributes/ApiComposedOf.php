<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class ApiComposedOf
{
    public function __construct(
        public readonly string $composition,
        public readonly array $refs = [],
        public readonly ?int $forStatus = null,
    ) {
        if (! in_array($composition, ['oneOf', 'anyOf', 'allOf'], true)) {
            throw new \InvalidArgumentException(
                "ApiComposedOf: \$composition must be 'oneOf', 'anyOf', or 'allOf', got '{$composition}'"
            );
        }
    }
}
