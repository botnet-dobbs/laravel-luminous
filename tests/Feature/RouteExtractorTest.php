<?php

namespace Botnetdobbs\Luminous\Tests\Feature;

use Botnetdobbs\Luminous\Extractors\ExtractedRoute;
use Botnetdobbs\Luminous\Extractors\RouteExtractor;
use Botnetdobbs\Luminous\LuminousServiceProvider;
use Botnetdobbs\Luminous\Tests\Fixtures\Controllers\InvokeController;
use Botnetdobbs\Luminous\Tests\Fixtures\Controllers\TestAttributeController;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;

class RouteExtractorTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LuminousServiceProvider::class];
    }

    private function makeExtractor(array $config = []): RouteExtractor
    {
        return new RouteExtractor(array_merge([
            'exclude_routes' => ['luminous.*'],
            'include_routes' => [],
        ], $config), $this->app['router']);
    }

    public function test_extracts_controller_routes(): void
    {
        Route::get('/test', [TestAttributeController::class, 'store']);

        $routes = $this->makeExtractor()->extract();

        $found = collect($routes)->first(fn (ExtractedRoute $r) => $r->path === '/test');
        $this->assertNotNull($found);
        $this->assertSame('get', $found->httpMethod);
        $this->assertSame(TestAttributeController::class, $found->controllerClass);
        $this->assertSame('store', $found->methodName);
    }

    public function test_excludes_closure_routes(): void
    {
        Route::get('/closure', fn () => 'hi');

        $routes = $this->makeExtractor()->extract();

        $this->assertNotContains('/closure', collect($routes)->pluck('path')->all());
    }

    public function test_excludes_routes_by_name_wildcard(): void
    {
        Route::get('/docs/json', [TestAttributeController::class, 'store'])->name('luminous.json');

        $routes = $this->makeExtractor(['exclude_routes' => ['luminous.*']])->extract();

        $this->assertNotContains('luminous.json', collect($routes)->pluck('routeName')->all());
    }

    public function test_api_ignore_on_method_excludes_that_method(): void
    {
        Route::get('/internal', [TestAttributeController::class, 'internalMethod']);
        Route::get('/store', [TestAttributeController::class, 'store']);

        $routes = $this->makeExtractor()->extract();
        $paths = collect($routes)->pluck('path')->all();

        $this->assertNotContains('/internal', $paths);
        $this->assertContains('/store', $paths);
    }

    public function test_optional_route_params_normalized(): void
    {
        Route::get('/items/{id?}', [TestAttributeController::class, 'store']);

        $routes = $this->makeExtractor()->extract();

        $found = collect($routes)->first(fn ($r) => str_contains($r->path, 'items'));
        $this->assertNotNull($found);
        $this->assertSame('/items/{id}', $found->path);
    }

    public function test_head_routes_excluded(): void
    {
        Route::get('/things', [TestAttributeController::class, 'store']);

        $routes = $this->makeExtractor()->extract();
        $methods = collect($routes)->where('path', '/things')->pluck('httpMethod')->all();

        $this->assertContains('get', $methods);
        $this->assertNotContains('head', $methods);
    }

    public function test_invoke_controllers_supported(): void
    {
        Route::get('/invoker', InvokeController::class);

        $routes = $this->makeExtractor()->extract();

        $found = collect($routes)->first(fn ($r) => $r->path === '/invoker');
        $this->assertNotNull($found);
        $this->assertSame('__invoke', $found->methodName);
        $this->assertSame(InvokeController::class, $found->controllerClass);
    }

    public function test_include_routes_restricts_output(): void
    {
        Route::get('/included', [TestAttributeController::class, 'store'])->name('api.included');
        Route::get('/excluded', [TestAttributeController::class, 'store'])->name('other.excluded');

        $routes = $this->makeExtractor(['include_routes' => ['api.*']])->extract();
        $names = collect($routes)->pluck('routeName')->all();

        $this->assertContains('api.included', $names);
        $this->assertNotContains('other.excluded', $names);
    }

    public function test_include_routes_wildcard_does_not_match_longer_prefix(): void
    {
        Route::get('/admin', [TestAttributeController::class, 'store'])->name('admin.index');
        Route::get('/administrator', [TestAttributeController::class, 'store'])->name('administrator.index');

        $routes = $this->makeExtractor(['include_routes' => ['admin.*']])->extract();
        $names = collect($routes)->pluck('routeName')->all();

        $this->assertContains('admin.index', $names);
        $this->assertNotContains('administrator.index', $names);
    }

    public function test_include_routes_exact_name_match(): void
    {
        Route::get('/ping', [TestAttributeController::class, 'store'])->name('ping');
        Route::get('/pong', [TestAttributeController::class, 'store'])->name('pong');

        $routes = $this->makeExtractor(['include_routes' => ['ping']])->extract();
        $names = collect($routes)->pluck('routeName')->all();

        $this->assertContains('ping', $names);
        $this->assertNotContains('pong', $names);
    }
}
