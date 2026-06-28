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
        return response()
            ->json($this->getSpec())
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function yaml(): Response
    {
        try {
            $output = $this->yaml->export($this->getSpec());

            return response($output, 200, [
                'Content-Type' => 'application/yaml; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\RuntimeException $e) {
            logger()->warning('luminous: YAML export failed', ['error' => $e->getMessage()]);

            return response(
                'YAML export is unavailable. Run: composer require symfony/yaml',
                501,
                ['Content-Type' => 'text/plain']
            );
        }
    }

    public function ui(): Response
    {
        $uiConfig = config('luminous.ui');
        $cdnBase = $uiConfig['cdn']['swagger_ui'] ?? '';
        if (! str_starts_with((string) $cdnBase, 'https://')) {
            $uiConfig['cdn']['swagger_ui'] = 'https://unpkg.com/swagger-ui-dist@5.18.2';
        }

        return response()
            ->view('luminous::swagger-ui', [
                'title' => config('luminous.info.title'),
                'specUrl' => route('luminous.json'),
                'uiConfig' => $uiConfig,
            ])
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    private function getSpec(): array
    {
        return $this->cache->get() ?? tap($this->generator->generate(), fn ($s) => $this->cache->put($s));
    }
}
