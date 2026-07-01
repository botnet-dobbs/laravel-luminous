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
        private readonly TagRegistry $tagRegistry,
    ) {}

    public function generate(): array
    {
        $this->registry->reset();
        $this->tagRegistry->reset();
        $this->registerSharedSchemas();

        $paths = [];
        $seenOperationIds = [];

        foreach ($this->routeExtractor->extract() as $route) {
            try {
                $operation = $this->controllerExtractor->extract($route);
                if (empty($operation)) {
                    continue;
                }
                $id = $operation['operationId'] ?? '';
                if ($id !== '') {
                    if (isset($seenOperationIds[$id])) {
                        $seenOperationIds[$id]++;
                        $operation['operationId'] = $id.'_'.$seenOperationIds[$id];
                    } else {
                        $seenOperationIds[$id] = 1;
                    }
                }
                $paths[$route->path][$route->httpMethod] = $operation;
            } catch (\Throwable $e) {
                logger()->warning('Luminous: failed to extract route [{method} {path}]: {type} {message}', [
                    'method' => $route->httpMethod,
                    'path' => $route->path,
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $paths = collect($paths)->sortKeys()->all();

        $tags = collect($this->tagRegistry->all())
            ->sortBy('name')
            ->values()
            ->all();

        $this->validateTagParents($tags);

        $doc = [
            'openapi' => '3.2.0',
            'info' => $this->buildInfo(),
            'servers' => $this->config['servers'] ?? [],
            'tags' => $tags,
            'paths' => $paths,
            'components' => $this->buildComponents(),
        ];

        if ($selfUrl = $this->config['self_url'] ?? null) {
            $doc['$self'] = $selfUrl;
        }

        return $doc;
    }

    private function buildInfo(): array
    {
        $infoConfig = $this->config['info'] ?? [];

        $info = [
            'title' => $infoConfig['title'] ?? 'Luminous API',
            'version' => $infoConfig['version'] ?? '1.0.0',
        ];

        if (! empty($infoConfig['description'])) {
            $info['description'] = $infoConfig['description'];
        }

        $contact = collect($infoConfig['contact'] ?? [])->filter()->all();
        if (! empty($contact)) {
            $info['contact'] = $contact;
        }

        $license = collect($infoConfig['license'] ?? [])->filter()->all();
        if (! empty($license)) {
            $info['license'] = $license;
        }

        return $info;
    }

    private function validateTagParents(array $tags): void
    {
        $tagNames = collect($tags)->pluck('name')->flip()->all();

        foreach ($tags as $tag) {
            if (isset($tag['parent']) && ! isset($tagNames[$tag['parent']])) {
                logger()->warning(
                    "Luminous: tag '{$tag['name']}' references parent '{$tag['parent']}' ".
                    "which is not declared. Add #[ApiTag('{$tag['parent']}')]".
                    ' to a controller.'
                );
            }
        }
    }

    private function registerSharedSchemas(): void
    {
        foreach ($this->config['shared_schemas'] ?? [] as $name => $schema) {
            if (is_array($schema)) {
                $this->registry->registerAnonymous($name, $schema);
            }
        }

        if ($this->config['include_pagination_schema'] ?? true) {
            $this->registry->registerAnonymous('PaginationMeta', [
                'type' => 'object',
                'properties' => [
                    'cursor' => ['type' => ['string', 'null']],
                    'has_more' => ['type' => 'boolean'],
                    'total' => ['type' => 'integer'],
                ],
            ]);
        }
    }

    private function buildComponents(): array
    {
        $components = ['schemas' => $this->registry->all()];

        $securitySchemes = $this->config['security_schemes'] ?? [];
        if (! empty($securitySchemes)) {
            $components['securitySchemes'] = $securitySchemes;
        }

        return $components;
    }
}
