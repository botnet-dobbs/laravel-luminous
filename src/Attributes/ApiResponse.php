<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiResponse
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $resource = null,
        public readonly string $description = '',
        public readonly bool $collection = false,
        public readonly bool $paginated = false,
        public readonly ?string $ref = null,
    ) {}

    public function isCollection(): bool
    {
        return $this->collection || $this->paginated;
    }
}
