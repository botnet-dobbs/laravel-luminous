<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiQuery
{
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly string $type = 'string',
        public readonly bool $required = false,
        public readonly mixed $example = null,
        public readonly array $enum = [],
        public readonly bool $deprecated = false,
    ) {}
}
