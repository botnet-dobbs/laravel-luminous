<?php

namespace Botnetdobbs\Luminous\Extractors;

use Botnetdobbs\Luminous\Generator\ComponentsRegistry;

class ControllerExtractor
{
    public function __construct(
        private readonly ComponentsRegistry $registry,
        private readonly array $config,
    ) {}

    public function extract(ExtractedRoute $route): array
    {
        $schemas = $this->registry->all();
        $security = $this->config['default_security'] ?? [];

        return [];
    }
}
