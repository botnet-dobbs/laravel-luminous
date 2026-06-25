<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ApiItems
{
    public function __construct(
        public readonly ?string $ref = null,
        public readonly ?string $type = null,
        public readonly ?string $format = null,
        public readonly array $enum = [],
    ) {}
}
