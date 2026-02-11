<?php

namespace Hanafalah\LaravelSupport\Commands;

use Hanafalah\LaravelSupport\Services\SetupBuilder;
use Hanafalah\LaravelSupport\Services\SetupCacheService;
use Illuminate\Console\Command;

class SetupCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:cache
                            {project=backbone : Project name (backbone, lite, hq, plus, gateway)}
                            {--clear : Clear the cache instead of regenerating}
                            {--info : Show cache information}
                            {--force : Force regenerate even if cache is valid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and cache service provider setup to Redis for improved performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $project = $this->argument('project');
        $projectName = $this->resolveProjectName($project);
        $projectConfig = $projectName;

        $this->info("Project: {$projectName}");

        $cache = new SetupCacheService($projectName);

        // Handle --info option
        if ($this->option('info')) {
            return $this->showInfo($cache);
        }

        // Handle --clear option
        if ($this->option('clear')) {
            return $this->clearCache($cache, $projectName);
        }

        // Check if cache is valid and --force not specified
        if ($cache->exists() && !$this->option('force')) {
            $this->warn("Cache already exists and is valid. Use --force to regenerate.");
            return $this->showInfo($cache);
        }

        return $this->generateCache($cache, $projectConfig, $projectName);
    }

    /**
     * Resolve the full project name from short name
     */
    protected function resolveProjectName(string $project): string
    {
        $projectMap = [
            'backbone' => 'wellmed-backbone',
            'lite' => 'wellmed-lite',
            'hq' => 'wellmed-hq',
            'plus' => 'wellmed-plus',
            'gateway' => 'wellmed-gateway',
            'satu-sehat' => 'wellmed-satu-sehat',
        ];

        // If it's a short name, expand it
        if (isset($projectMap[$project])) {
            return $projectMap[$project];
        }

        // If it already has 'wellmed-' prefix, use as-is
        if (str_starts_with($project, 'wellmed-')) {
            return $project;
        }

        // Default: prepend 'wellmed-'
        return 'wellmed-' . $project;
    }

    /**
     * Get the service provider class for a project
     */
    protected function getProviderClass(string $projectName): ?string
    {
        $providerMap = [
            'wellmed-backbone' => \Projects\WellmedBackbone\Providers\WellmedBackboneServiceProvider::class,
            'wellmed-lite' => \Projects\WellmedLite\Providers\WellmedLiteServiceProvider::class ?? null,
            'wellmed-hq' => \Projects\Hq\Providers\HqServiceProvider::class ?? null,
            'wellmed-plus' => \Projects\WellmedPlus\Providers\WellmedPlusServiceProvider::class ?? null,
        ];

        return $providerMap[$projectName] ?? null;
    }

    /**
     * Show cache information
     */
    protected function showInfo(SetupCacheService $cache): int
    {
        $info = $cache->getInfo();

        $this->newLine();
        $this->line("<fg=cyan>Cache Information:</>");

        $this->table(
            ['Property', 'Value'],
            [
                ['Cache Key', $info['cache_key']],
                ['Exists', $info['exists'] ? '<fg=green>Yes</>' : '<fg=red>No</>'],
                ['Valid', $info['valid'] ? '<fg=green>Yes</>' : '<fg=yellow>No (version mismatch)</>'],
                ['Current Version', substr($info['current_version'], 0, 12) . '...'],
                ['Cached Version', $info['cached_version'] ? substr($info['cached_version'], 0, 12) . '...' : '<fg=gray>N/A</>'],
                ['Generated At', $info['generated_at'] ?? '<fg=gray>N/A</>'],
                ['Models Count', $info['models_count']],
                ['Contracts Count', $info['contracts_count']],
                ['Providers Count', $info['providers_count']],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Clear the cache
     */
    protected function clearCache(SetupCacheService $cache, string $projectName): int
    {
        $cache->invalidate();
        $this->info("Cache cleared for {$projectName}");

        return Command::SUCCESS;
    }

    /**
     * Generate the cache
     */
    protected function generateCache(SetupCacheService $cache, string $projectConfig, string $projectName): int
    {
        $this->info("Generating setup cache for {$projectName}...");
        $this->newLine();

        // Get the project's config
        $config = config($projectConfig);

        if (!$config) {
            $this->error("Config not found: {$projectConfig}");
            $this->line("Make sure the project is loaded and config is available.");
            return Command::FAILURE;
        }

        $packages = $config['packages'] ?? [];

        if (empty($packages)) {
            $this->warn("No packages found in config. Cache will only include project-level models/contracts.");
        }

        // Get the provider class
        $providerClass = $this->getProviderClass($projectName);

        if (!$providerClass || !class_exists($providerClass)) {
            $this->warn("Provider class not found for {$projectName}. Using config-only scanning.");
            $providerClass = '';
        }

        $builder = new SetupBuilder();

        $startTime = microtime(true);

        try {
            $setup = $cache->regenerate(function () use ($builder, $projectConfig, $packages, $providerClass) {
                return $builder->buildSetupData($projectConfig, $packages, $providerClass);
            });

            $elapsed = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("Cache generated successfully!");
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Generation Time', "{$elapsed} ms"],
                    ['Models', count($setup['models'] ?? [])],
                    ['Contracts', count($setup['contracts'] ?? [])],
                    ['Providers', count($setup['providers'] ?? [])],
                    ['Version', substr($setup['version'] ?? '', 0, 12) . '...'],
                    ['Generated At', $setup['generated_at'] ?? 'N/A'],
                ]
            );

            // Show some model examples
            if (!empty($setup['models'])) {
                $this->newLine();
                $this->line("<fg=cyan>Sample Models (first 10):</>");
                $models = array_slice($setup['models'], 0, 10, true);
                foreach ($models as $key => $class) {
                    $this->line("  <fg=gray>{$key}</> => {$class}");
                }
                if (count($setup['models']) > 10) {
                    $this->line("  <fg=gray>... and " . (count($setup['models']) - 10) . " more</>");
                }
            }

            $this->newLine();
            $this->info("Run 'php artisan octane:reload' to apply changes.");

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Failed to generate cache: {$e->getMessage()}");
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
