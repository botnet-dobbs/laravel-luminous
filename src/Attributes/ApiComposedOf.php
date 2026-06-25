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
        assert(
            in_array($composition, ['oneOf', 'anyOf', 'allOf'], true),
            "ApiComposedOf: \$composition must be 'oneOf', 'anyOf', or 'allOf', got '{$composition}'"
        );
    }
}
