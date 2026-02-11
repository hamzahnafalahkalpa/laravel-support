<?php

namespace Hanafalah\LaravelSupport\Providers;

use Illuminate\Support\ServiceProvider;
use Hanafalah\LaravelSupport\Commands;

class CommandServiceProvider extends ServiceProvider
{
    protected $__commands = [
        Commands\InstallMakeCommand::class,
        Commands\AddPackageCommand::class,
        Commands\ElasticsearchIndexCommand::class,
        Commands\GetElasticsearchIndexCommand::class,
        Commands\DeleteElasticsearchIndexCommand::class,
        Commands\SetupCacheCommand::class,
        Commands\SetupClearCommand::class,
        Commands\SetupStatusCommand::class,
        Commands\SetupProfileCommand::class,
    ];

    public function register()
    {
        // Merge config commands with default commands to ensure all are registered
        $configCommands = config('laravel-support.commands', []);
        $commands = array_unique(array_merge($configCommands, $this->__commands));
        $this->commands($commands);
    }

    public function provides()
    {
        return $this->__commands;
    }
}
