<?php

namespace Botnetdobbs\Luminous\Extractors;

final readonly class ExtractedRoute
{
    public function __construct(
        public string $httpMethod,
        public string $path,
        public string $controllerClass,
        public string $methodName,
        public string $routeName,
        public array $middlewares,
    ) {}
}
