<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiHeader
{
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly bool $required = false,
        public readonly string $type = 'string',
        public readonly ?string $format = null,
        public readonly mixed $example = null,
    ) {}
}
