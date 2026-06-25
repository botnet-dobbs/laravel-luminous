<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiTag
{
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
    ) {}
}
