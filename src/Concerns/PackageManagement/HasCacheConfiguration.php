<?php

namespace Hanafalah\LaravelSupport\Concerns\PackageManagement;

/**
 * This trait is used to handle cache configuration on package management
 * it provide basic cache management for package management
 * so you can cache your configuration and just get it from cache if you need it
 */
trait HasCacheConfiguration
{
    protected array $__cache = [];

    /**
     * Get the cache value for a given key
     *
     * @param  string  $key
     * @return array
     */
    protected function getCache(string $key): array
    {
        return $this->__cache[$key];
    }

    /**
     * Sets a cache configuration value for a given key.
     *
     * @param string $key The key for which the configuration is being set.
     * @param array $value The configuration value to set.
     *
     * @return self
     */
    protected function setCache(string $key, array $value): self
    {
        $this->__cache[$key] = $value;
        return $this;
    }

    /**
     * Sets the cache name for a given cache key.
     *
     * @param string $key The cache key for which the name is being set.
     * @param string $name The name to set for the cache.
     *
     * @return self
     */
    protected function setCacheName(string $key, string $name): self
    {
        $this->__cache[$key]['name'] = $name;
        return $this;
    }

    /**
     * Sets the cache tags for a given cache key.
     *
     * @param string $key The cache key for which the tags are being set.
     * @param string|array $tags The tags to set for the cache. If an array is provided, each tag will be set individually.
     *
     * @return self Returns the instance for method chaining.
     */
    protected function setCacheTags(string $key, string|array $tags): self
    {
        $tags = $this->mustArray($tags);
        $this->__cache[$key]['tags'] = $tags;
        return $this;
    }

    /**
     * Sets the cache duration for a given cache key.
     *
     * @param string $key The cache key for which the duration is being set.
     * @param string|int $duration The duration to set for the cache. Use 'forever' to cache indefinitely.
     * @return self Returns the instance for method chaining.
     */
    protected function setCacheDuration(string $key, string|int $duration): self
    {
        if ($duration == 'forever') {
            unset($this->__cache[$key]['duration']);
            $this->__cache[$key]['forever'] = true;
        } else {
            unset($this->__cache[$key]['forever']);
            $this->__cache[$key]['duration'] = $duration;
        }
        return $this;
    }
}
