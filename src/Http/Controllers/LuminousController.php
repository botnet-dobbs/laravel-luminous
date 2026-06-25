<?php

namespace Botnetdobbs\Luminous\Http\Controllers;

use Botnetdobbs\Luminous\Generator\OpenApiGenerator;
use Botnetdobbs\Luminous\Support\CacheManager;
use Botnetdobbs\Luminous\Support\YamlExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class LuminousController extends Controller
{
    public function __construct(
        private readonly OpenApiGenerator $generator,
        private readonly CacheManager $cache,
        private readonly YamlExporter $yaml,
    ) {}

    public function json(): JsonResponse
    {
        return response()->json($this->getSpec());
    }

    public function yaml(): Response
    {
        try {
            $output = $this->yaml->export($this->getSpec());

            return response($output, 200, ['Content-Type' => 'application/yaml; charset=utf-8']);
        } catch (\RuntimeException $e) {
            return response($e->getMessage(), 501, ['Content-Type' => 'text/plain']);
        }
    }

    public function ui(): Response
    {
        return response()->view('luminous::swagger-ui', [
            'title' => config('luminous.info.title'),
            'specUrl' => route('luminous.json'),
            'uiConfig' => config('luminous.ui'),
        ]);
    }

    private function getSpec(): array
    {
        return $this->cache->get() ?? tap($this->generator->generate(), fn ($s) => $this->cache->put($s));
    }
}
