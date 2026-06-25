<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class ApiBody
{
    public function __construct(
        public readonly string $request,
        public readonly string $description = '',
        public readonly bool $required = true,
        public readonly string $mediaType = 'application/json',
    ) {}
}
