<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait OctaneSafeCache
{
    /**
     * Get a cache key with user context to prevent data leakage between users.
     *
     * @param string $key
     * @param int|null $userId
     * @return string
     */
    protected function getUserCacheKey(string $key, ?int $userId = null): string
    {
        $userId = $userId ?? auth()->id();
        
        if (!$userId) {
            throw new \RuntimeException('Cannot create user cache key without user ID');
        }
        
        return "user.{$userId}.{$key}";
    }

    /**
     * Store data in cache with user context.
     *
     * @param string $key
     * @param mixed $value
     * @param \DateTimeInterface|\DateInterval|int|null $ttl
     * @param int|null $userId
     * @return bool
     */
    protected function userCachePut(string $key, $value, $ttl = null, ?int $userId = null): bool
    {
        return Cache::put($this->getUserCacheKey($key, $userId), $value, $ttl);
    }

    /**
     * Get data from cache with user context.
     *
     * @param string $key
     * @param mixed $default
     * @param int|null $userId
     * @return mixed
     */
    protected function userCacheGet(string $key, $default = null, ?int $userId = null)
    {
        return Cache::get($this->getUserCacheKey($key, $userId), $default);
    }

    /**
     * Remove data from cache with user context.
     *
     * @param string $key
     * @param int|null $userId
     * @return bool
     */
    protected function userCacheForget(string $key, ?int $userId = null): bool
    {
        return Cache::forget($this->getUserCacheKey($key, $userId));
    }

    /**
     * Remember data in cache with user context.
     *
     * @param string $key
     * @param \DateTimeInterface|\DateInterval|int|null $ttl
     * @param \Closure $callback
     * @param int|null $userId
     * @return mixed
     */
    protected function userCacheRemember(string $key, $ttl, \Closure $callback, ?int $userId = null)
    {
        return Cache::remember($this->getUserCacheKey($key, $userId), $ttl, $callback);
    }
}