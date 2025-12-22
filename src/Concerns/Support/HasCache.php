<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

trait HasCache
{
    use Conditionable;
    protected array $__cache = [];

    protected function isUsingCache(): bool{
        return true;
    }

    protected function validForCache(): bool{
        return ! collect(request()->keys())
            ->contains(fn($key) => Str::startsWith($key, 'search_'));
    }


    public function cacheWhen(bool $condition, array $cache, callable $callback)
    {
        //SEMENTARA AUTO FALSE DULU
        return $this->when(config('laravel-support.cache.enabled', true) && $this->isUsingCache() && $this->validForCache() && $condition, function () use ($cache, $callback) {
            return $this->setCache($cache, function () use ($callback) {
                return $callback();
            });
        }, function () use ($callback) {
            return $callback();
        });
    }

    public function setCache(array $cacheData, callable $callback, bool $with_page = true, bool $update = false){        
        $cacheData = array_merge([
            'name'     => null,
            'duration' => config('cache.ttl') ?? 3600, // default seconds
            'tags'     => null,
            'forever'  => false,
            'page'     => null, // optional override (useful for CLI)
        ], $cacheData);

        if (empty($cacheData['name'])) {
            throw new \InvalidArgumentException('Cache name is required.');
        }
        return $this->cacheDriver(function($cache_driver) use ($cacheData, $callback, $with_page, $update) {
            $cache = Cache::store($cache_driver);
            // safe page suffix (dont rely blindly on request() in console)
            if ($with_page) {
                $page = $cacheData['page'] ?? (app()->runningInConsole() ? null : request()->query('page', null));
                if (!empty($page)) {
                    $cacheData['name'] .= '_' . $page;
                }
            }

            // normalize tags to array
            $tags = is_array($cacheData['tags']) ? $cacheData['tags'] : (is_null($cacheData['tags']) ? [] : [(string) $cacheData['tags']]);
            if (count($tags) > 0) {
                try {
                    $cache = $cache->tags($tags);
                } catch (\BadMethodCallException $e) {
                    // fallback: store tags into key prefix and log warning
                    \Log::warning('Cache store does not support tags; falling back to prefixed key.', [
                        'key' => $cacheData['name'],
                        'tags' => $tags,
                        'exception' => $e->getMessage()
                    ]);
                    $cacheData['name'] = implode('_', $tags) . '_' . $cacheData['name'];
                    // keep $cache as-is (no tags)
                }
            }

            // forever vs timed
            if (!empty($cacheData['forever'])) {
                $cache->forever($cacheData['name'], $callback());
                $result = $cache->get($cacheData['name']);
                return $result;
            }

            // normalize duration: allow numeric seconds or DateInterval/DateTimeInterface
            $duration = $cacheData['duration'];
            if (is_numeric($duration)) {
                $ttl = now()->addSeconds((int) $duration);
            } elseif ($duration instanceof \DateInterval || $duration instanceof \DateTimeInterface) {
                $ttl = $duration;
            } else {
                $ttl = now()->addSeconds((int) (config('cache.ttl') ?? 3600));
            }

            if ($update) {
                $cache->put($cacheData['name'], $callback(), $ttl);
                $result = $cache->get($cacheData['name']);
                return $result;
            }else{
                return $cache->remember($cacheData['name'], $ttl, function () use ($callback) {
                    return $callback();
                });
            }
        });
    }

    public function getCache($key, mixed $tags = null){
        return $this->cacheDriver(function($cache_driver) use ($key, $tags) {
            $cache = Cache::store($cache_driver);

            if (!empty($tags) && $cache_driver === 'redis') {
                $cache = $cache->tags($tags); // <— harus assign ulang ke $cache
            }
            return $cache->get($key);
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

    public function forgetTags(string|array $tags = [])
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
