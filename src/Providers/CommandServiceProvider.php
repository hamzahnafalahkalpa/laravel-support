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
        Commands\DeleteElasticsearchIndexCommand::class
    ];

    public function register()
    {
        $this->commands(config('laravel-support.commands', $this->__commands));
    }

    public function provides()
    {
        return $this->__commands;
    }
}
