<?php

namespace Hanafalah\LaravelSupport\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class SetupCacheService
{
    protected string $cacheKey;
    protected int $ttl = 604800; // 7 days in seconds
    protected string $redisConnection = 'setup';

    /**
     * Create a new SetupCacheService instance.
     *
     * @param string $projectName The project name (e.g., 'wellmed-backbone', 'wellmed-lite')
     */
    public function __construct(string $projectName)
    {
        // Key format: wellmed-backbone-setup, wellmed-lite-setup, etc.
        // NOT tenant-specific - this is application-level caching
        $this->cacheKey = $projectName . '-setup';
    }

    /**
     * Get setup from Redis or generate if not exists
     *
     * @param callable $generator Function that generates the setup data
     * @return array The setup data
     */
    public function getOrGenerate(callable $generator): array
    {
        $version = $this->getVersion();
        $cached = $this->getFromRedis();

        // Check if cache exists and version matches
        if ($cached && ($cached['version'] ?? null) === $version) {
            Log::debug("[SetupCache] Loaded from cache: {$this->cacheKey}");
            return $cached;
        }

        Log::info("[SetupCache] Generating fresh setup for: {$this->cacheKey}");

        // Generate fresh setup
        $setup = $generator();
        $setup['version'] = $version;
        $setup['generated_at'] = now()->toIso8601String();

        $this->saveToRedis($setup);

        return $setup;
    }

    /**
     * Force regenerate cache
     *
     * @param callable $generator Function that generates the setup data
     * @return array The newly generated setup data
     */
    public function regenerate(callable $generator): array
    {
        $this->invalidate();
        return $this->getOrGenerate($generator);
    }

    /**
     * Check if cache exists and is valid
     *
     * @return bool
     */
    public function exists(): bool
    {
        $cached = $this->getFromRedis();
        if (!$cached) {
            return false;
        }

        return ($cached['version'] ?? null) === $this->getVersion();
    }

    /**
     * Get version based on composer.lock hash
     * This ensures cache is invalidated when dependencies change
     *
     * @return string
     */
    public function getVersion(): string
    {
        $lockFile = base_path('composer.lock');
        if (File::exists($lockFile)) {
            return md5_file($lockFile);
        }

        // Fallback to app key if composer.lock doesn't exist
        return md5(config('app.key', 'default'));
    }

    /**
     * Get cached data from Redis
     *
     * @return array|null
     */
    protected function getFromRedis(): ?array
    {
        try {
            // Use dedicated setup Redis connection (not affected by tenancy)
            $connection = $this->getRedisConnection();
            $data = $connection->get($this->cacheKey);

            return $data ? json_decode($data, true) : null;
        } catch (\Throwable $e) {
            Log::warning("[SetupCache] Failed to read from Redis: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Save setup data to Redis
     *
     * @param array $data
     * @return void
     */
    protected function saveToRedis(array $data): void
    {
        try {
            $connection = $this->getRedisConnection();
            $connection->setex(
                $this->cacheKey,
                $this->ttl,
                json_encode($data)
            );

            Log::info("[SetupCache] Saved to cache: {$this->cacheKey}");
        } catch (\Throwable $e) {
            Log::error("[SetupCache] Failed to save to Redis: {$e->getMessage()}");
        }
    }

    /**
     * Get the Redis connection for setup caching
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function getRedisConnection()
    {
        // Try to use dedicated 'setup' connection first
        // Falls back to 'default' if 'setup' connection not configured
        try {
            return Redis::connection($this->redisConnection);
        } catch (\Throwable $e) {
            Log::debug("[SetupCache] Setup connection not available, using default");
            return Redis::connection('default');
        }
    }

    /**
     * Invalidate the cache
     *
     * @return void
     */
    public function invalidate(): void
    {
        try {
            $connection = $this->getRedisConnection();
            $connection->del($this->cacheKey);

            Log::info("[SetupCache] Invalidated cache: {$this->cacheKey}");
        } catch (\Throwable $e) {
            Log::warning("[SetupCache] Failed to invalidate cache: {$e->getMessage()}");
        }
    }

    /**
     * Apply cached setup to Laravel application
     *
     * @param array $setup The cached setup data
     * @return void
     */
    public function apply(array $setup): void
    {
        // Apply morphMap for models
        if (!empty($setup['models'])) {
            Relation::morphMap($setup['models']);
            config(['database.models' => array_merge(
                config('database.models', []),
                $setup['models']
            )]);
        }

        // Apply contract bindings
        if (!empty($setup['contracts'])) {
            $shortNameContracts = [];

            foreach ($setup['contracts'] as $contract => $implementation) {
                // Skip short name entries (no backslash) - regenerate them below
                if (!str_contains($contract, '\\')) {
                    continue;
                }

                if (!app()->bound($contract) && class_exists($implementation)) {
                    app()->singleton($contract, $implementation);
                }

                // Short name => CONTRACT INTERFACE (consistent with module convention)
                // e.g., "IntegrationData" => "Projects\WellmedBackbone\Contracts\Data\ModulePatient\IntegrationData"
                $shortName = class_basename($contract);
                if (!isset($shortNameContracts[$shortName])) {
                    $shortNameContracts[$shortName] = $contract;
                }
            }

            // Only add short name mappings to config('app.contracts')
            // Full namespace entries are for container bindings only, not config access
            config(['app.contracts' => array_merge(
                config('app.contracts', []),
                $shortNameContracts
            )]);
        }

        // Apply config overrides
        if (!empty($setup['config_overrides'])) {
            foreach ($setup['config_overrides'] as $key => $value) {
                config([$key => $value]);
            }
        }

        Log::debug("[SetupCache] Applied setup: " . count($setup['models'] ?? []) . " models, " . count($setup['contracts'] ?? []) . " contracts");
    }

    /**
     * Get cache key
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }

    /**
     * Get cache info for debugging
     *
     * @return array
     */
    public function getInfo(): array
    {
        $cached = $this->getFromRedis();

        return [
            'cache_key' => $this->cacheKey,
            'exists' => $cached !== null,
            'valid' => $this->exists(),
            'current_version' => $this->getVersion(),
            'cached_version' => $cached['version'] ?? null,
            'generated_at' => $cached['generated_at'] ?? null,
            'models_count' => count($cached['models'] ?? []),
            'contracts_count' => count($cached['contracts'] ?? []),
            'providers_count' => count($cached['providers'] ?? []),
        ];
    }
}
