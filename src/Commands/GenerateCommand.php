<?php

namespace Botnetdobbs\Luminous\Commands;

use Botnetdobbs\Luminous\Generator\OpenApiGenerator;
use Botnetdobbs\Luminous\Support\CacheManager;
use Illuminate\Console\Command;

class GenerateCommand extends Command
{
    protected $signature = 'luminous:generate
                            {--force : Regenerate even if cache is warm}
                            {--validate : Validate the generated spec against OpenAPI 3.1 rules}';

    protected $description = 'Generate the OpenAPI spec and store it in cache';

    private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];

    public function handle(OpenApiGenerator $generator, CacheManager $cache): int
    {
        if ($this->option('force')) {
            $cache->flush();
            $this->line('<comment>Cache cleared.</comment>');
        }

        $this->info('Generating OpenAPI 3.1 spec...');
        $start = microtime(true);
        $spec = $generator->generate();
        $ms = round((microtime(true) - $start) * 1000);
        $cache->put($spec);

        $pathCount = count($spec['paths'] ?? []);
        $schemaCount = count($spec['components']['schemas'] ?? []);

        $this->info("Done in {$ms}ms.");
        $this->table(['Metric', 'Count'], [
            ['Paths', $pathCount],
            ['Schemas in components', $schemaCount],
        ]);

        try {
            $url = route('luminous.json');
            $this->line("Spec: <href={$url}>{$url}</>");
        } catch (\Exception) {
            // Routes disabled — omit the link
        }

        if ($this->option('validate')) {
            return $this->validateSpec($spec);
        }

        return self::SUCCESS;
    }

    private function validateSpec(array $spec): int
    {
        $errors = [];

        if (empty($spec['openapi'])) {
            $errors[] = 'Missing openapi field';
        }
        if (empty($spec['info']['title'])) {
            $errors[] = 'Missing info.title';
        }
        if (empty($spec['info']['version'])) {
            $errors[] = 'Missing info.version';
        }
        if (empty($spec['paths'])) {
            $errors[] = 'No paths defined';
        }

        foreach ($spec['paths'] ?? [] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (! in_array($method, self::HTTP_METHODS, true)) {
                    continue;
                }
                if (empty($operation['responses'])) {
                    $errors[] = "No responses defined for {$method} {$path}";
                }
            }
        }

        if (! empty($errors)) {
            $this->error('Validation errors:');
            foreach ($errors as $err) {
                $this->line("  - {$err}");
            }

            return self::FAILURE;
        }

        $this->info('Spec validation passed (basic checks).');
        $this->line('<comment>For full OpenAPI 3.1 validation: npx @redocly/cli lint openapi.json</comment>');

        return self::SUCCESS;
    }
}
