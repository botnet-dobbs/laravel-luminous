<?php

namespace Botnetdobbs\Luminous\Generator;

class ComponentsRegistry
{
    private array $schemas = [];

    private array $classIndex = [];

    public function reset(): void
    {
        $this->schemas = [];
        $this->classIndex = [];
    }

    public function register(string $class, array $schema): string
    {
        $name = $this->toSchemaName($class);

        if (! isset($this->schemas[$name])) {
            $this->schemas[$name] = $schema;
            $this->classIndex[$class] = $name;
        }

        return "#/components/schemas/{$name}";
    }

    public function registerAnonymous(string $name, array $schema): string
    {
        $this->schemas[$name] = $schema;

        return "#/components/schemas/{$name}";
    }

    public function isRegistered(string $class): bool
    {
        return isset($this->classIndex[$class]);
    }

    public function refFor(string $class): ?string
    {
        if (! $this->isRegistered($class)) {
            return null;
        }

        return '#/components/schemas/'.$this->classIndex[$class];
    }

    public function all(): array
    {
        return $this->schemas;
    }

    private function toSchemaName(string $class): string
    {
        $parts = explode('\\', ltrim($class, '\\'));
        $base = array_pop($parts);

        // Disambiguate if base name is already taken by a different FQCN
        if (isset($this->schemas[$base]) && ($this->classIndex[$class] ?? null) !== $base) {
            $parent = array_pop($parts);

            if ($parent) {
                return "{$parent}_{$base}";
            }

            // No parent namespace available. Use numeric suffix to avoid silent collision
            $i = 2;
            while (isset($this->schemas["{$base}_{$i}"])) {
                $i++;
            }

            return "{$base}_{$i}";
        }

        return $base;
    }
}
