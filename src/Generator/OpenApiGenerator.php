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
        $this->registerSharedSchemas();

        $paths = [];
        $rawTagObjects = [];
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
                foreach ($operation['x-luminous-tags'] ?? [] as $tagObj) {
                    $rawTagObjects[$tagObj['name']] = $tagObj;
                }
                unset($operation['x-luminous-tags']);
                $paths[$route->path][$route->httpMethod] = $operation;
            } catch (\Throwable $e) {
                logger()->warning('Luminous: failed to extract route [{method} {path}]: {type}', [
                    'method' => $route->httpMethod,
                    'path' => $route->path,
                    'type' => get_class($e),
                ]);
            }
        }

        $paths = collect($paths)->sortKeys()->all();

        $tags = collect($rawTagObjects)
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'openapi' => '3.1.0',
            'info' => $this->buildInfo(),
            'servers' => $this->config['servers'] ?? [],
            'tags' => $tags,
            'paths' => $paths,
            'components' => $this->buildComponents(),
        ];
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

    private function registerSharedSchemas(): void
    {
        $this->registry->registerAnonymous('ErrorResponse', [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'string'],
                'message' => ['type' => 'string'],
                'request_id' => ['type' => 'string'],
                'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                'details' => ['type' => 'object'],
            ],
        ]);

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
