<?php

namespace Botnetdobbs\Luminous\Commands;

use Botnetdobbs\Luminous\Generator\OpenApiGenerator;
use Botnetdobbs\Luminous\Support\CacheManager;
use Botnetdobbs\Luminous\Support\YamlExporter;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    protected $signature = 'luminous:export
                            {--format=json : Output format: json or yaml}
                            {--output= : File path to write (default: stdout)}
                            {--pretty : Pretty-print JSON}
                            {--no-cache : Skip cache, regenerate fresh}';

    protected $description = 'Export the OpenAPI spec to stdout or a file';

    public function handle(OpenApiGenerator $generator, CacheManager $cache, YamlExporter $yaml): int
    {
        $spec = ($this->option('no-cache') ? null : $cache->get()) ?? $generator->generate();
        $format = strtolower($this->option('format'));

        if (! in_array($format, ['json', 'yaml'], true)) {
            $this->error("Unsupported format: {$format}. Use json or yaml.");

            return self::FAILURE;
        }

        if ($format === 'yaml') {
            try {
                $output = $yaml->export($spec);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        } else {
            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            if ($this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }
            try {
                $output = json_encode($spec, $flags | JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->error('JSON encoding failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $outputPath = $this->option('output');
        if ($outputPath) {
            if (! $this->validateOutputPath($outputPath)) {
                return self::FAILURE;
            }
            $resolvedPath = realpath(dirname($outputPath)).DIRECTORY_SEPARATOR.basename($outputPath);
            if (file_put_contents($resolvedPath, $output, LOCK_EX) === false) {
                $this->error("Could not write to {$outputPath}");

                return self::FAILURE;
            }
            $this->info("Spec written to {$outputPath}");
        } else {
            $this->line($output);
        }

        return self::SUCCESS;
    }

    private function validateOutputPath(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['json', 'yaml', 'yml'], true)) {
            $this->error('Output file must have a .json, .yaml, or .yml extension.');

            return false;
        }

        $dir = realpath(dirname($path));
        if ($dir === false) {
            $this->error('Output directory does not exist: '.dirname($path));

            return false;
        }

        return true;
    }
}
