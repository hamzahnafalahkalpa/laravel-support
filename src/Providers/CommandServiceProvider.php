<?php

namespace Zahzah\LaravelSupport\Providers;

use Illuminate\Support\ServiceProvider;
use Zahzah\LaravelSupport\Commands;

class CommandServiceProvider extends ServiceProvider
{
    protected $__commands = [
        Commands\InstallMakeCommand::class
    ];

    public function register(){
        $this->commands(config('laravel-support.commands',$this->__commands));
    }

    public function provides(){
        return $this->__commands;
    }
}
