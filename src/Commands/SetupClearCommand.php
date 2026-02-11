<?php

namespace Hanafalah\LaravelSupport\Commands;

use Hanafalah\LaravelSupport\Services\SetupCacheService;
use Illuminate\Console\Command;

class SetupClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:clear
                            {project? : Project name (backbone, lite, hq, plus, gateway). If not provided, clears all.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the Redis setup cache for a project or all projects';

    /**
     * Available projects
     */
    protected array $projects = [
        'backbone' => 'wellmed-backbone',
        'lite' => 'wellmed-lite',
        'hq' => 'wellmed-hq',
        'plus' => 'wellmed-plus',
        'gateway' => 'wellmed-gateway',
        'satu-sehat' => 'wellmed-satu-sehat',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $project = $this->argument('project');

        if ($project) {
            return $this->clearSingleProject($project);
        }

        return $this->clearAllProjects();
    }

    /**
     * Clear cache for a single project
     */
    protected function clearSingleProject(string $project): int
    {
        $projectName = $this->resolveProjectName($project);

        $cache = new SetupCacheService($projectName);
        $existed = $cache->exists();
        $cache->invalidate();

        if ($existed) {
            $this->info("✓ Cleared cache for {$projectName}");
        } else {
            $this->warn("Cache was not set for {$projectName}");
        }

        return Command::SUCCESS;
    }

    /**
     * Clear cache for all projects
     */
    protected function clearAllProjects(): int
    {
        $this->info("Clearing setup cache for all projects...\n");

        $cleared = 0;
        $notSet = 0;

        foreach ($this->projects as $short => $full) {
            $cache = new SetupCacheService($full);

            if ($cache->exists()) {
                $cache->invalidate();
                $this->line("  <fg=green>✓</> {$full}");
                $cleared++;
            } else {
                $this->line("  <fg=gray>-</> {$full} <fg=gray>(not set)</>");
                $notSet++;
            }
        }

        $this->newLine();
        $this->info("Cleared: {$cleared}, Not set: {$notSet}");

        return Command::SUCCESS;
    }

    /**
     * Resolve the full project name from short name
     */
    protected function resolveProjectName(string $project): string
    {
        // If it's a short name, expand it
        if (isset($this->projects[$project])) {
            return $this->projects[$project];
        }

        // If it already has 'wellmed-' prefix, use as-is
        if (str_starts_with($project, 'wellmed-')) {
            return $project;
        }

        // Default: prepend 'wellmed-'
        return 'wellmed-' . $project;
    }
}
