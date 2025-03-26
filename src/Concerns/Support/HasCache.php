<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Str;

trait HasCache
{
    use Conditionable;
    protected array $__cache;

    protected function cacheWhen(bool $condition, array $cache, callable $callback)
    {
        //SEMENTARA AUTO FALSE DULU
        return $this->when(config('laravel-support.cache.enabled', true) && $condition, function () use ($cache, $callback) {
            return $this->setCache($cache, function () use ($callback) {
                return $callback();
            });
        }, function () use ($callback) {
            return $callback();
        });
    }

    protected function setCache(array $cacheData, callable $callback, bool $with_page = true)
    {
        $cache = cache();
        if (isset($cacheData['tags']) && count($cacheData['tags']) > 0) $cache = $cache->tags($cacheData['tags']);
        if ($with_page) {
            $cacheData['name'] .= (request()->has('page') ? '_' . request()->get('page') : '');
        }
        return (isset($cacheData['forever']) && $cacheData['forever'] === true)
            ? $cache->rememberForever($cacheData['name'], function () use ($cacheData, $callback) {
                return $callback();
            })
            : $cache->remember($cacheData['name'], $cacheData['duration'], function () use ($callback) {
                return $callback();
            });
    }


    public function getCache($key, mixed $tags = null, $default = null)
    {
        return $this->cacheDriver(function($cache_driver) use ($key,$tags,$default){
            $cache = cache();
            if (isset($tags) && $cache_driver == 'redis') $cache = $cache->tags($tags);
            return $cache->get($key, $default);
        });
    }


    public function forgetKey($key, mixed $tags = null)
    {
        return $this->cacheDriver(function($cache_driver) use ($key,$tags){
            $cache = cache();
            if (isset($tags) && $cache_driver == 'redis') $cache = $cache->tags($tags);
            return $cache->forget($key);
        });
    }

    public function forgetTags(mixed $tags = [])
    {
        return $this->cacheDriver(function($cache_driver) use ($tags){
            if ($cache_driver === 'redis') {
                $tags = $this->mustArray($tags);
                return Cache::tags($tags)->flush();
            }
            return null;
        });
    }

    public function cacheDriver(callable $callback){
        $cache_driver = config('cache.default','database');
        return $callback($cache_driver);
    }

    /**
     * Merges the given key with the given additionals array in the __cache property.
     *
     * @param string $key The key to be merged.
     * @param array $additionals The additionals array to be merged.
     * @return void
     */
    public function mergeCacheName(&$cache, $additionals): void
    {
        $cache['name'] .= '-' . $additionals;
    }

    public function addSuffixCache(&$cache, mixed $target_tags, $suffix): void
    {
        $target_tags ??= [];
        $target_tags = $this->mustArray($target_tags);
        $suffix = implode('-', $this->mustArray($suffix));
        $suffix = Str::snake($suffix, '-');
        $this->mergeCacheName($cache, $suffix);
        foreach ($target_tags as $tag) {
            $src = \array_search($tag, $cache['tags']);
            $cache['tags'][$src] .= '-' . $suffix;
        }
    }
}
