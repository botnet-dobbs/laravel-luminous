<?php

namespace Botnetdobbs\Luminous\Extractors;

use Botnetdobbs\Luminous\Attributes\ApiIgnore;
use Illuminate\Routing\Route;

class RouteExtractor
{
    public function __construct(private readonly array $config) {}

    public function extract(): array
    {
        $routes = [];

        foreach (app('router')->getRoutes() as $route) {
            $action = $route->getActionName();

            if (! $action || str_contains($action, 'Closure') || $action === 'Closure') {
                continue;
            }

            [$controllerClass, $methodName] = $this->parseAction($action);

            if (! $controllerClass || ! $methodName) {
                continue;
            }
            if (! class_exists($controllerClass)) {
                continue;
            }
            if ($this->shouldExclude($route)) {
                continue;
            }
            if ($this->hasIgnoreAttribute($controllerClass, $methodName)) {
                continue;
            }

            $methods = collect($route->methods())
                ->reject(fn ($m) => in_array(strtoupper($m), ['HEAD', 'OPTIONS'], true))
                ->all();

            foreach ($methods as $method) {
                $routes[] = new ExtractedRoute(
                    httpMethod: strtolower($method),
                    path: $this->normalizePath($route->uri()),
                    controllerClass: $controllerClass,
                    methodName: $methodName,
                    routeName: $route->getName() ?? '',
                    middlewares: $route->middleware(),
                );
            }
        }

        return $routes;
    }

    private function parseAction(string $action): array
    {
        if (str_contains($action, '@')) {
            return explode('@', $action, 2);
        }

        if (class_exists($action) && method_exists($action, '__invoke')) {
            return [$action, '__invoke'];
        }

        return [null, null];
    }

    private function normalizePath(string $uri): string
    {
        $path = preg_replace('/\{(\w+)\?\}/', '{$1}', $uri);

        return '/'.ltrim($path, '/');
    }

    private function shouldExclude(Route $route): bool
    {
        $name = $route->getName() ?? '';
        $uri = $route->uri();

        foreach ($this->config['exclude_routes'] ?? [] as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                // Use substr to strip exactly '.*'. rtrim would strip a character set, not a suffix
                $prefix = substr($pattern, 0, -2);
                if (str_starts_with($name, $prefix.'.')) {
                    return true;
                }
            }

            if (fnmatch($pattern, $uri)) {
                return true;
            }
            if ($name === $pattern) {
                return true;
            }
        }

        $includes = $this->config['include_routes'] ?? [];

        if (! empty($includes)) {
            foreach ($includes as $pattern) {
                if (str_ends_with($pattern, '.*')) {
                    $prefix = substr($pattern, 0, -2);
                    if (str_starts_with($name, $prefix.'.') || $name === $prefix) {
                        return false;
                    }
                } elseif ($name === $pattern || fnmatch($pattern, $uri)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function hasIgnoreAttribute(string $class, string $method): bool
    {
        try {
            $classRef = new \ReflectionClass($class);

            if (collect($classRef->getAttributes(ApiIgnore::class))->isNotEmpty()) {
                return true;
            }

            if ($classRef->hasMethod($method)) {
                if (collect($classRef->getMethod($method)->getAttributes(ApiIgnore::class))->isNotEmpty()) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return true;
        }

        return false;
    }
}
