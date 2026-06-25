<?php

namespace Botnetdobbs\Luminous\Support;

use Symfony\Component\Yaml\Yaml;

class YamlExporter
{
    public function isAvailable(): bool
    {
        return class_exists(Yaml::class);
    }

    public function export(array $spec): string
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException(
                'YAML export requires symfony/yaml. Install it with: composer require symfony/yaml'
            );
        }

        return Yaml::dump(
            $spec,
            20,
            2,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        );
    }
}
