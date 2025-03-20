<?php

namespace Hanafalah\LaravelSupport\Commands;

use Hanafalah\LaravelSupport\Concerns\ServiceProvider\HasMigrationConfiguration;
use Hanafalah\LaravelSupport\Concerns\Support\HasMicrotenant;

class EnvironmentCommand extends BaseCommand
{
    use HasMigrationConfiguration;
    use HasMicrotenant;

    protected function init(): self
    {
        //INITIALIZE SECTION
        $this->initConfig()->setLocalConfig('laravel-support');
        return $this;
    }

    protected function dir(): string
    {
        return __DIR__ . '/../';
    }
}
