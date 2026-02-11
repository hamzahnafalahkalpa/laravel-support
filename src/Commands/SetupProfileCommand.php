<?php

namespace Hanafalah\LaravelSupport\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

class SetupProfileCommand extends Command
{
    protected $signature = 'setup:profile
                            {project=backbone : Project name}
                            {--detailed : Show detailed timing for each provider}';

    protected $description = 'Profile service provider boot times to identify performance bottlenecks';

    protected array $timings = [];
    protected float $startTime;

    public function handle(): int
    {
        $project = $this->argument('project');
        $projectName = $this->resolveProjectName($project);

        $this->info("Profiling boot performance for: {$projectName}");
        $this->newLine();

        // Show current config
        $this->showConfig();

        // Show boot timing breakdown
        $this->showBootTiming();

        // Show recommendations
        $this->showRecommendations();

        return Command::SUCCESS;
    }

    protected function showConfig(): void
    {
        $this->line("<fg=cyan>Current Configuration:</>");

        $this->table(['Setting', 'Value'], [
            ['USE_REDIS_SETUP_CACHE', config('laravel-support.use_redis_setup_cache') ? 'true' : 'false'],
            ['SETUP_CACHE_AUTO_GENERATE', config('laravel-support.setup_cache.auto_generate', true) ? 'true' : 'false'],
            ['Registered Providers', count(app()->getLoadedProviders())],
            ['Deferred Providers', count(app()->getDeferredServices())],
        ]);

        $this->newLine();
    }

    protected function showBootTiming(): void
    {
        $this->line("<fg=cyan>Boot Timing Analysis:</>");
        $this->newLine();

        // Get loaded providers
        $loadedProviders = app()->getLoadedProviders();

        $providerGroups = [
            'Laravel Core' => [],
            'Wellmed Projects' => [],
            'Hanafalah Repositories' => [],
            'Features' => [],
            'Third Party' => [],
        ];

        foreach ($loadedProviders as $provider => $loaded) {
            if (str_starts_with($provider, 'Illuminate\\')) {
                $providerGroups['Laravel Core'][] = $provider;
            } elseif (str_starts_with($provider, 'Projects\\')) {
                $providerGroups['Wellmed Projects'][] = $provider;
            } elseif (str_starts_with($provider, 'Hanafalah\\')) {
                $providerGroups['Hanafalah Repositories'][] = $provider;
            } elseif (str_starts_with($provider, 'Features\\')) {
                $providerGroups['Features'][] = $provider;
            } else {
                $providerGroups['Third Party'][] = $provider;
            }
        }

        $rows = [];
        foreach ($providerGroups as $group => $providers) {
            $rows[] = [$group, count($providers)];
        }
        $rows[] = ['<fg=yellow>TOTAL</>', '<fg=yellow>' . count($loadedProviders) . '</>'];

        $this->table(['Provider Group', 'Count'], $rows);
        $this->newLine();

        // Show detailed providers if requested
        if ($this->option('detailed')) {
            $this->showDetailedProviders($providerGroups);
        }
    }

    protected function showDetailedProviders(array $groups): void
    {
        foreach ($groups as $group => $providers) {
            if (empty($providers)) continue;

            $this->line("<fg=yellow>{$group} (" . count($providers) . "):</>");
            foreach ($providers as $provider) {
                $this->line("  - " . class_basename($provider));
            }
            $this->newLine();
        }
    }

    protected function showRecommendations(): void
    {
        $this->line("<fg=cyan>Performance Recommendations:</>");
        $this->newLine();

        $loadedCount = count(app()->getLoadedProviders());
        $deferredCount = count(app()->getDeferredServices());

        // Check provider count
        if ($loadedCount > 100) {
            $this->warn("⚠ High provider count ({$loadedCount}). Consider:");
            $this->line("  - Lazy loading non-critical providers");
            $this->line("  - Using deferred providers for rarely used services");
            $this->line("  - Combining related providers");
            $this->newLine();
        }

        // Check cache status
        if (!config('laravel-support.use_redis_setup_cache')) {
            $this->warn("⚠ Setup cache is DISABLED. Enable for faster model/contract resolution:");
            $this->line("  USE_REDIS_SETUP_CACHE=true");
            $this->newLine();
        }

        // Check Octane config
        $octaneServer = env('OCTANE_SERVER');
        if ($octaneServer) {
            $this->info("✓ Octane configured with: {$octaneServer}");
            $this->line("  Note: Artisan commands run outside Octane. HTTP requests benefit from Octane caching.");
            $this->newLine();
        } else {
            $this->warn("⚠ OCTANE_SERVER not set. Consider using Octane for better performance.");
            $this->newLine();
        }

        // Suggest profiling
        $this->line("<fg=gray>For detailed timing, add this to WellmedBackboneServiceProvider::boot():</>");
        $this->newLine();
        $this->line('<fg=gray>  $start = microtime(true);</fg>');
        $this->line('<fg=gray>  // ... your code ...</fg>');
        $this->line('<fg=gray>  \\Log::info("Boot time: " . round((microtime(true) - $start) * 1000) . "ms");</fg>');
        $this->newLine();
    }

    protected function resolveProjectName(string $project): string
    {
        $map = [
            'backbone' => 'wellmed-backbone',
            'lite' => 'wellmed-lite',
            'hq' => 'wellmed-hq',
            'plus' => 'wellmed-plus',
            'gateway' => 'wellmed-gateway',
        ];

        return $map[$project] ?? 'wellmed-' . $project;
    }
}
