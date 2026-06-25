<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiParam
{
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly string $type = 'string',
        public readonly string $format = '',
        public readonly bool $required = true,
        public readonly mixed $example = null,
    ) {}
}
