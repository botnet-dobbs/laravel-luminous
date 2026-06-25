<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ApiProperty
{
    public function __construct(
        public readonly string $description = '',
        public readonly mixed $example = null,
        public readonly ?string $format = null,
        public readonly bool $nullable = false,
        public readonly ?int $minimum = null,
        public readonly ?int $maximum = null,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly array $enum = [],
        public readonly bool $readOnly = false,
        public readonly bool $writeOnly = false,
        public readonly ?string $ref = null,
        public readonly ?string $itemsRef = null,
        public readonly ?string $itemsType = null,
    ) {}
}
