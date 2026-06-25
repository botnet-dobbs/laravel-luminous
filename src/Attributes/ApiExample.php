<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiExample
{
    public function __construct(
        public readonly string $name,
        public readonly string $summary = '',
        public readonly mixed $value = [],
        public readonly string $type = 'request',
        public readonly int $status = 200,
        public readonly string $mediaType = 'application/json',
    ) {
        assert(
            in_array($type, ['request', 'response'], true),
            "ApiExample: \$type must be 'request' or 'response', got '{$type}'"
        );
    }
}
