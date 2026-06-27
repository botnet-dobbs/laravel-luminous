<?php

namespace Botnetdobbs\Luminous\Tests\Feature;

use Botnetdobbs\Luminous\LuminousServiceProvider;
use Orchestra\Testbench\TestCase;

class ArtisanCommandsNoRoutesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LuminousServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('luminous.enabled', true);
    }

    public function test_generate_validate_fails_when_no_paths(): void
    {
        $this->artisan('luminous:generate', ['--validate' => true])
            ->expectsOutputToContain('No paths defined')
            ->assertExitCode(1);
    }
}
