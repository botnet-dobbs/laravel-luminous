<?php

namespace Botnetdobbs\Luminous\Extractors;

use Botnetdobbs\Luminous\Attributes\ApiBody;
use Botnetdobbs\Luminous\Attributes\ApiComposedOf;
use Botnetdobbs\Luminous\Attributes\ApiDeprecated;
use Botnetdobbs\Luminous\Attributes\ApiExample;
use Botnetdobbs\Luminous\Attributes\ApiHeader;
use Botnetdobbs\Luminous\Attributes\ApiIgnore;
use Botnetdobbs\Luminous\Attributes\ApiNoSecurity;
use Botnetdobbs\Luminous\Attributes\ApiOperation;
use Botnetdobbs\Luminous\Attributes\ApiParam;
use Botnetdobbs\Luminous\Attributes\ApiQuery;
use Botnetdobbs\Luminous\Attributes\ApiResponse;
use Botnetdobbs\Luminous\Attributes\ApiSecurity;
use Botnetdobbs\Luminous\Attributes\ApiTag;
use Illuminate\Foundation\Http\FormRequest;

class ControllerExtractor
{
    private const DEFAULT_MEDIA_TYPE = 'application/json';

    public function __construct(
        private readonly RequestExtractor $requestExtractor,
        private readonly ResourceExtractor $resourceExtractor,
        private readonly array $config,
    ) {}

    public function extract(ExtractedRoute $route): array
    {
        if (! class_exists($route->controllerClass)) {
            return [];
        }

        $classRef = new \ReflectionClass($route->controllerClass);

        if (! empty($classRef->getAttributes(ApiIgnore::class))) {
            return [];
        }

        $methodRef = $classRef->getMethod($route->methodName);

        if (! empty($methodRef->getAttributes(ApiIgnore::class))) {
            return [];
        }

        $operation = [];
        $operationId = null;

        $operationAttrs = $methodRef->getAttributes(ApiOperation::class);
        if (! empty($operationAttrs)) {
            $apiOp = $operationAttrs[0]->newInstance();
            $operation['summary'] = $apiOp->summary;
            if ($apiOp->description !== '') {
                $operation['description'] = $apiOp->description;
            }
            $operationId = $apiOp->operationId;
        } else {
            $operation['summary'] = ucfirst($route->methodName);
        }

        $deprecatedAttrs = $methodRef->getAttributes(ApiDeprecated::class);
        if (! empty($deprecatedAttrs)) {
            $deprecated = $deprecatedAttrs[0]->newInstance();
            $operation['deprecated'] = true;
            $notice = 'Deprecated: '.$deprecated->reason;
            if ($deprecated->replacement) {
                $notice .= '. Use '.$deprecated->replacement.' instead.';
            }
            $existing = $operation['description'] ?? '';
            $operation['description'] = $existing ? $notice."\n\n".$existing : $notice;
        }

        $tags = $this->buildTags($classRef, $methodRef);
        $operation['tags'] = $tags;
        $operation['operationId'] = $operationId ?? $this->generateOperationId($route, $tags);
        $operation['security'] = $this->buildSecurity($classRef, $methodRef);

        $parameters = $this->buildParameters($methodRef);
        if (! empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        $requestBody = $this->buildRequestBody($methodRef);
        if ($requestBody !== null) {
            $operation['requestBody'] = $requestBody;
        }

        $exampleInstances = collect($methodRef->getAttributes(ApiExample::class))
            ->map(fn ($a) => $a->newInstance())
            ->all();

        $responses = $this->buildResponses($methodRef, $exampleInstances);

        foreach ($exampleInstances as $example) {
            if ($example->type === 'request' && isset($operation['requestBody'])) {
                $operation['requestBody']['content'][$example->mediaType]['examples'][$example->name] = [
                    'summary' => $example->summary,
                    'value' => $example->value,
                ];
            }
        }

        $responses = $this->applyComposedOf($methodRef, $responses);

        if (! isset($responses['500'])) {
            $responses['500'] = ['description' => 'Internal server error'];
        }

        $operation['responses'] = $responses;

        return $operation;
    }

    private function buildTags(\ReflectionClass $classRef, \ReflectionMethod $methodRef): array
    {
        return collect($classRef->getAttributes(ApiTag::class))
            ->map(fn ($a) => $a->newInstance()->name)
            ->merge(
                collect($methodRef->getAttributes(ApiTag::class))
                    ->map(fn ($a) => $a->newInstance()->name)
            )
            ->unique()
            ->values()
            ->all();
    }

    private function buildSecurity(\ReflectionClass $classRef, \ReflectionMethod $methodRef): array
    {
        if (! empty($methodRef->getAttributes(ApiNoSecurity::class))) {
            return [];
        }

        $methodSecAttrs = $methodRef->getAttributes(ApiSecurity::class);
        if (! empty($methodSecAttrs)) {
            return collect($methodSecAttrs)
                ->map(function ($a) {
                    $sec = $a->newInstance();

                    return [$sec->scheme => $sec->scopes];
                })
                ->all();
        }

        $classSecAttrs = $classRef->getAttributes(ApiSecurity::class);
        if (! empty($classSecAttrs)) {
            return collect($classSecAttrs)
                ->map(function ($a) {
                    $sec = $a->newInstance();

                    return [$sec->scheme => $sec->scopes];
                })
                ->all();
        }

        return $this->config['default_security'] ?? [];
    }

    private function buildParameters(\ReflectionMethod $methodRef): array
    {
        $parameters = [];

        foreach ($methodRef->getAttributes(ApiParam::class) as $attr) {
            $param = $attr->newInstance();
            $schema = ['type' => $param->type];
            if ($param->format !== '') {
                $schema['format'] = $param->format;
            }
            if ($param->example !== null) {
                $schema['example'] = $param->example;
            }

            $entry = [
                'name' => $param->name,
                'in' => 'path',
                'required' => true,
                'schema' => $schema,
            ];
            if ($param->description !== '') {
                $entry['description'] = $param->description;
            }
            $parameters[] = $entry;
        }

        foreach ($methodRef->getAttributes(ApiQuery::class) as $attr) {
            $query = $attr->newInstance();
            $schema = ['type' => $query->type];
            if ($query->example !== null) {
                $schema['example'] = $query->example;
            }
            if (! empty($query->enum)) {
                $schema['enum'] = $query->enum;
            }

            $entry = [
                'name' => $query->name,
                'in' => 'query',
                'required' => $query->required,
                'schema' => $schema,
            ];
            if ($query->description !== '') {
                $entry['description'] = $query->description;
            }
            if ($query->deprecated) {
                $entry['deprecated'] = true;
            }
            $parameters[] = $entry;
        }

        foreach ($methodRef->getAttributes(ApiHeader::class) as $attr) {
            $header = $attr->newInstance();
            $schema = ['type' => $header->type];
            if ($header->format !== null) {
                $schema['format'] = $header->format;
            }
            if ($header->example !== null) {
                $schema['example'] = $header->example;
            }

            $entry = [
                'name' => $header->name,
                'in' => 'header',
                'required' => $header->required,
                'schema' => $schema,
            ];
            if ($header->description !== '') {
                $entry['description'] = $header->description;
            }
            $parameters[] = $entry;
        }

        return $parameters;
    }

    private function buildRequestBody(\ReflectionMethod $methodRef): ?array
    {
        $bodyAttrs = $methodRef->getAttributes(ApiBody::class);
        if (! empty($bodyAttrs)) {
            $body = $bodyAttrs[0]->newInstance();
            $schema = $this->requestExtractor->extract($body->request);
            $requestBody = [
                'required' => $body->required,
                'content' => [
                    $body->mediaType => ['schema' => $schema],
                ],
            ];
            if ($body->description !== '') {
                $requestBody['description'] = $body->description;
            }

            return $requestBody;
        }

        $autoBody = $this->autoDetectFormRequest($methodRef);
        if ($autoBody !== null) {
            $mediaType = $this->requestExtractor->mediaType($autoBody);

            return [
                'required' => true,
                'content' => [
                    $mediaType => [
                        'schema' => $this->requestExtractor->extract($autoBody),
                    ],
                ],
            ];
        }

        return null;
    }

    private function autoDetectFormRequest(\ReflectionMethod $methodRef): ?string
    {
        $autoBody = null;
        foreach ($methodRef->getParameters() as $param) {
            $reflType = $param->getType();
            $type = $reflType instanceof \ReflectionNamedType ? $reflType->getName() : null;
            if ($type && class_exists($type) && is_subclass_of($type, FormRequest::class)) {
                $autoBody = $type;
            }
        }

        return $autoBody;
    }

    private function buildResponses(\ReflectionMethod $methodRef, array $exampleInstances): array
    {
        $responses = [];

        foreach ($methodRef->getAttributes(ApiResponse::class) as $attr) {
            $response = $attr->newInstance();
            $status = (string) $response->status;

            if ($response->ref !== null) {
                $responses[$status] = [
                    'description' => $response->description,
                    'content' => [
                        self::DEFAULT_MEDIA_TYPE => [
                            'schema' => ['$ref' => $response->ref],
                        ],
                    ],
                ];
            } elseif ($response->resource !== null) {
                $resourceSchema = $this->resourceExtractor->extract($response->resource);
                $schema = $response->isCollection()
                    ? ['type' => 'array', 'items' => $resourceSchema]
                    : $resourceSchema;

                if ($this->config['wrap_responses'] ?? false) {
                    $key = $this->config['response_wrapper_key'] ?? 'data';
                    if (empty($key) || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $key)) {
                        logger()->warning('luminous: invalid response_wrapper_key; falling back to "data"');
                        $key = 'data';
                    }
                    $wrapped = [
                        'type' => 'object',
                        'properties' => [$key => $schema],
                        'required' => [$key],
                    ];
                    if ($response->paginated && ($this->config['include_pagination_schema'] ?? true)) {
                        $wrapped['properties']['pagination'] = ['$ref' => '#/components/schemas/PaginationMeta'];
                        $wrapped['required'][] = 'pagination';
                    }
                    $schema = $wrapped;
                }

                $responses[$status] = [
                    'description' => $response->description,
                    'content' => [
                        self::DEFAULT_MEDIA_TYPE => ['schema' => $schema],
                    ],
                ];
            } else {
                $responses[$status] = ['description' => $response->description];
            }
        }

        foreach ($exampleInstances as $example) {
            if ($example->type === 'response') {
                $status = (string) $example->status;
                if (isset($responses[$status])) {
                    $responses[$status]['content'][$example->mediaType]['examples'][$example->name] = [
                        'summary' => $example->summary,
                        'value' => $example->value,
                    ];
                }
            }
        }

        return $responses;
    }

    private function applyComposedOf(\ReflectionMethod $methodRef, array $responses): array
    {
        foreach ($methodRef->getAttributes(ApiComposedOf::class) as $attr) {
            $composed = $attr->newInstance();
            $schemas = collect($composed->refs)
                ->map(fn ($classOrRef) => class_exists($classOrRef)
                    ? $this->resourceExtractor->extract($classOrRef)
                    : ['$ref' => $classOrRef]
                )
                ->all();

            $composedSchema = [$composed->composition => $schemas];

            if ($composed->forStatus !== null) {
                $key = (string) $composed->forStatus;
                if (isset($responses[$key]) && ! isset($responses[$key]['content'])) {
                    $responses[$key]['content'][self::DEFAULT_MEDIA_TYPE]['schema'] = $composedSchema;
                }
            } else {
                $applied = false;
                foreach ($responses as &$responseObj) {
                    if (! isset($responseObj['content'])) {
                        $responseObj['content'][self::DEFAULT_MEDIA_TYPE]['schema'] = $composedSchema;
                        $applied = true;
                        break;
                    }
                }
                unset($responseObj);

                if (! $applied) {
                    logger()->warning(
                        "ApiComposedOf on {$methodRef->class}::{$methodRef->name}: no description-only response found; composition schema was not applied."
                    );
                }
            }
        }

        return $responses;
    }

    private function generateOperationId(ExtractedRoute $route, array $tags): string
    {
        $tag = ! empty($tags) ? $tags[0] : $this->autoTag($route->controllerClass);

        if ($route->methodName === '__invoke') {
            return $route->httpMethod.ucfirst($tag);
        }

        return $route->httpMethod.ucfirst($tag).ucfirst($route->methodName);
    }

    private function autoTag(string $controllerClass): string
    {
        $basename = class_basename($controllerClass);

        return str_ends_with($basename, 'Controller')
            ? substr($basename, 0, -10)
            : $basename;
    }
}
