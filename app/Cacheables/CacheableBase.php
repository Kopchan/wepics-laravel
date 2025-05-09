<?php

namespace App\Cacheables;

use Illuminate\Support\Facades\Cache;

abstract class CacheableBase implements CacheableInterface
{
    public const TTL = null;

    static public function getKey(array $args = []): string
    {
        return static::class . ':' . implode(',', $args);
    }

    static public function get(...$args): static
    {
        $result = static::function($args);
        Cache::put(static::getKey($args), $result, static::TTL);
        return $result;
    }

    static public function getCached(...$args): static
    {
        return Cache::remember(static::getKey($args), static::TTL, fn() => static::function($args));
    }

    static public function forget(...$args): void
    {
        Cache::forget(static::getKey($args));
    }
}
