<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiSecurity
{
    public function __construct(
        public readonly string $scheme,
        public readonly array $scopes = [],
    ) {}
}
