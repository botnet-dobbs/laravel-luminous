<?php

namespace Botnetdobbs\Luminous\Extractors;

class RouteExtractor
{
    public function __construct(private readonly array $config) {}

    public function extract(): array
    {
        $excludes = $this->config['exclude_routes'] ?? [];
        $includes = $this->config['include_routes'] ?? [];

        return [];
    }
}
