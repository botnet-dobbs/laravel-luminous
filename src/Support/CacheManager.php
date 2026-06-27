<?php

namespace Botnetdobbs\Luminous\Support;

use Illuminate\Contracts\Cache\Repository;

class CacheManager
{
    private function store(): Repository
    {
        return cache()->store(config('luminous.cache.store'));
    }

    public function get(): ?array
    {
        if (! config('luminous.cache.enabled')) {
            return null;
        }

        return $this->store()->get(config('luminous.cache.key'));
    }

    public function put(array $spec): void
    {
        if (! config('luminous.cache.enabled')) {
            return;
        }

        $this->store()->put(config('luminous.cache.key'), $spec, config('luminous.cache.ttl'));
    }

    public function flush(): void
    {
        if (! config('luminous.cache.enabled')) {
            return;
        }

        $this->store()->forget(config('luminous.cache.key'));
    }
}
