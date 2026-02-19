<?php

namespace Hanafalah\LaravelSupport\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;

class SetupBuilder
{
    protected array $scannedPaths = [];

    /**
     * Build complete setup data by scanning all packages
     *
     * @param string $projectName The project config name (e.g., 'wellmed-backbone')
     * @param array $packages The packages configuration array
     * @param string $projectProviderClass The main project service provider class
     * @return array
     */
    public function buildSetupData(string $projectName, array $packages, string $projectProviderClass): array
    {
        $models = [];
        $contracts = [];
        $providers = [];
        $configOverrides = [];

        Log::debug("[SetupBuilder] Building setup for {$projectName} with " . count($packages) . " packages");

        // Scan all packages
        foreach ($packages as $packageKey => $package) {
            if (!isset($package['provider'])) {
                continue;
            }

            $providerClass = $this->normalizeClassName($package['provider']);

            if (!class_exists($providerClass)) {
                Log::warning("[SetupBuilder] Provider class not found: {$providerClass}");
                continue;
            }

            $providers[] = $providerClass;

            // Get models from package
            $packageModels = $this->scanModelsForProvider($providerClass);
            $models = array_merge($models, $packageModels);

            // Get contracts from package
            $packageContracts = $this->scanContractsForProvider($providerClass);
            $contracts = array_merge($contracts, $packageContracts);
        }

        // Scan main project
        if (class_exists($projectProviderClass)) {
            $projectModels = $this->scanModelsForProvider($projectProviderClass);
            $models = array_merge($models, $projectModels);

            $projectContracts = $this->scanContractsForProvider($projectProviderClass);
            $contracts = array_merge($contracts, $projectContracts);
        }

        // Merge with existing config-defined models/contracts
        $configModels = config("{$projectName}.database.models", []);
        $configContracts = config("{$projectName}.app.contracts", []);

        $models = array_merge($models, $configModels);
        $contracts = array_merge($contracts, $this->processConfigContracts($configContracts));

        // Add short name mappings for config('app.contracts.X') style access
        // Maps short name (e.g., 'IntegrationData') to CONTRACT INTERFACE
        // Consistent with module convention: "License" => "Hanafalah\ModuleLicense\Contracts\Schemas\License"
        $shortNameContracts = [];
        foreach ($contracts as $contract => $implementation) {
            $shortName = class_basename($contract);
            // Only add if not already exists (avoid overwriting explicit mappings)
            if (!isset($contracts[$shortName]) && !isset($shortNameContracts[$shortName])) {
                $shortNameContracts[$shortName] = $contract;
            }
        }
        $contracts = array_merge($contracts, $shortNameContracts);

        Log::info("[SetupBuilder] Built setup: " . count($models) . " models, " . count($contracts) . " contracts (incl. short names), " . count($providers) . " providers");

        return [
            'models' => $models,
            'contracts' => $contracts,
            'providers' => $providers,
            'config_overrides' => $configOverrides,
        ];
    }

    /**
     * Scan models for a specific provider class
     *
     * @param string $providerClass
     * @return array
     */
    public function scanModelsForProvider(string $providerClass): array
    {
        try {
            $reflection = new ReflectionClass($providerClass);
            $providerPath = dirname($reflection->getFileName());

            // Get the namespace prefix
            $namespace = $reflection->getNamespaceName();
            $namespacePrefix = Str::beforeLast($namespace, '\\');

            // Try different model paths
            $modelPaths = [
                $providerPath . '/../Models',
                $providerPath . '/Models',
            ];

            $models = [];
            foreach ($modelPaths as $path) {
                $realPath = realpath($path);
                if ($realPath && is_dir($realPath) && !isset($this->scannedPaths[$realPath . '_models'])) {
                    $this->scannedPaths[$realPath . '_models'] = true;
                    $scanned = $this->scanDirectory($realPath, $namespacePrefix . '\\Models');
                    $models = array_merge($models, $scanned);
                }
            }

            return $models;
        } catch (\Throwable $e) {
            Log::debug("[SetupBuilder] Failed to scan models for {$providerClass}: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Scan contracts for a specific provider class
     *
     * @param string $providerClass
     * @return array
     */
    public function scanContractsForProvider(string $providerClass): array
    {
        try {
            $reflection = new ReflectionClass($providerClass);
            $providerPath = dirname($reflection->getFileName());

            // Get the namespace prefix
            $namespace = $reflection->getNamespaceName();
            $namespacePrefix = Str::beforeLast($namespace, '\\');

            // Try different contract paths
            $contractPaths = [
                $providerPath . '/../Contracts',
                $providerPath . '/Contracts',
            ];

            $contracts = [];
            foreach ($contractPaths as $path) {
                $realPath = realpath($path);
                if ($realPath && is_dir($realPath) && !isset($this->scannedPaths[$realPath . '_contracts'])) {
                    $this->scannedPaths[$realPath . '_contracts'] = true;
                    $scanned = $this->scanContractDirectory($realPath, $namespacePrefix);
                    $contracts = array_merge($contracts, $scanned);
                }
            }

            return $contracts;
        } catch (\Throwable $e) {
            Log::debug("[SetupBuilder] Failed to scan contracts for {$providerClass}: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Scan a directory for model classes and build morphMap
     *
     * @param string $path
     * @param string $namespace
     * @return array
     */
    protected function scanDirectory(string $path, string $namespace): array
    {
        $models = [];

        if (!is_dir($path)) {
            return $models;
        }

        try {
            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = $file->getRelativePathname();
                $className = $namespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
                $className = $this->normalizeClassName($className);

                if (class_exists($className)) {
                    $baseName = class_basename($className);
                    // Use snake_case for morphMap key (Laravel convention)
                    $morphKey = Str::snake($baseName);
                    $models[$morphKey] = $className;
                }
            }
        } catch (\Throwable $e) {
            Log::debug("[SetupBuilder] Error scanning directory {$path}: {$e->getMessage()}");
        }

        return $models;
    }

    /**
     * Scan a directory for contract interfaces and find implementations
     *
     * @param string $path
     * @param string $namespacePrefix
     * @return array
     */
    protected function scanContractDirectory(string $path, string $namespacePrefix): array
    {
        $contracts = [];

        if (!is_dir($path)) {
            return $contracts;
        }

        try {
            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = $file->getRelativePathname();
                $contractClass = $namespacePrefix . '\\Contracts\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
                $contractClass = $this->normalizeClassName($contractClass);

                // Find implementation (remove 'Contracts\' from path)
                $implementationClass = str_replace('\\Contracts\\', '\\', $contractClass);
                $implementationClass = $this->normalizeClassName($implementationClass);

                // Check if both contract and implementation exist
                if (interface_exists($contractClass) && class_exists($implementationClass)) {
                    $contracts[$contractClass] = $implementationClass;
                } elseif (class_exists($contractClass) && class_exists($implementationClass)) {
                    // Some "contracts" might be abstract classes
                    $contracts[$contractClass] = $implementationClass;
                }
            }
        } catch (\Throwable $e) {
            Log::debug("[SetupBuilder] Error scanning contract directory {$path}: {$e->getMessage()}");
        }

        return $contracts;
    }

    /**
     * Process contracts from config array format
     *
     * @param array $configContracts
     * @return array
     */
    protected function processConfigContracts(array $configContracts): array
    {
        $contracts = [];

        foreach ($configContracts as $key => $value) {
            if (is_numeric($key)) {
                // Contract class name only - find implementation
                $contractClass = $this->normalizeClassName($value);
                $implementationClass = str_replace('\\Contracts\\', '\\', $contractClass);

                if (interface_exists($contractClass) && class_exists($implementationClass)) {
                    $contracts[$contractClass] = $implementationClass;
                }
            } else {
                // Skip short name entries (without backslash) - these are auto-generated, not config
                // Short names will be regenerated in buildSetupData after all contracts are collected
                if (!str_contains($key, '\\')) {
                    continue;
                }
                // Key-value pair: contract => implementation (full class names only)
                $contracts[$this->normalizeClassName($key)] = $this->normalizeClassName($value);
            }
        }

        return $contracts;
    }

    /**
     * Normalize class name by removing duplicate backslashes
     *
     * @param string $className
     * @return string
     */
    protected function normalizeClassName(string $className): string
    {
        return preg_replace('/\\\\+/', '\\', $className);
    }

    /**
     * Reset scanned paths cache (useful for testing)
     *
     * @return void
     */
    public function resetScannedPaths(): void
    {
        $this->scannedPaths = [];
    }
}
