<?php

namespace Botnetdobbs\Luminous\Generator;

use Botnetdobbs\Luminous\Extractors\ControllerExtractor;
use Botnetdobbs\Luminous\Extractors\RouteExtractor;

class OpenApiGenerator
{
    public function __construct(
        private readonly array $config,
        private readonly RouteExtractor $routeExtractor,
        private readonly ControllerExtractor $controllerExtractor,
        private readonly ComponentsRegistry $registry,
    ) {}

    public function generate(): array
    {
        $this->registry->reset();

        $paths = [];
        foreach ($this->routeExtractor->extract() as $route) {
            $paths[$route->path][$route->httpMethod] = $this->controllerExtractor->extract($route);
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->config['info']['title'] ?? 'Luminous API',
                'version' => $this->config['info']['version'] ?? '1.0.0',
            ],
            'paths' => $paths,
        ];
    }
}
