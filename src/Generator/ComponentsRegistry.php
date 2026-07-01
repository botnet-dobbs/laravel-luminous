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

    public function register(string $class, array $schema): void
    {
        $name = $this->toSchemaName($class);

        if (! isset($this->schemas[$name])) {
            $this->schemas[$name] = $schema;
        } elseif ($this->schemas[$name] !== $schema) {
            logger()->debug("Luminous: re-registration of [{$class}] ignored; use updateSchema() to overwrite.");
        }
        if (! isset($this->classIndex[$class])) {
            $this->classIndex[$class] = $name;
        }
    }

    public function registerAnonymous(string $name, array $schema): string
    {
        if (! preg_match('/^[a-zA-Z0-9.\-_]+$/', $name)) {
            throw new \InvalidArgumentException(
                "Schema name '{$name}' contains characters invalid in an OpenAPI component name."
            );
        }

        if (! isset($this->schemas[$name])) {
            $this->schemas[$name] = $schema;
        }

        return "#/components/schemas/{$name}";
    }

    public function updateSchema(string $class, array $schema): void
    {
        if (isset($this->classIndex[$class])) {
            $this->schemas[$this->classIndex[$class]] = $schema;
        }
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

    public function toSchemaName(string $class): string
    {
        $parts = explode('\\', ltrim($class, '\\'));
        $base = array_pop($parts);

        // Disambiguate if base name is already taken by a different FQCN
        if (isset($this->schemas[$base]) && ($this->classIndex[$class] ?? null) !== $base) {
            $parent = array_pop($parts);

            if ($parent) {
                $candidate = "{$parent}_{$base}";
                if (! isset($this->schemas[$candidate])
                    || ($this->classIndex[$class] ?? null) === $candidate) {
                    return $candidate;
                }
            }

            // No parent available, or parent-prefixed name is also taken. Use numeric suffix.
            $i = 2;
            while (isset($this->schemas["{$base}_{$i}"])) {
                $i++;
            }

            return "{$base}_{$i}";
        }

        return $base;
    }
}
