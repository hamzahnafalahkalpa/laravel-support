<?php

namespace Hanafalah\LaravelSupport\Commands;

use Hanafalah\LaravelSupport\{
    Commands\Concerns as CommandSupport,
    Concerns\Support as ConcernsSupport,
    Concerns
};
use Hanafalah\LaravelSupport\Concerns\Commands\PromptLayout;
use Hanafalah\LaravelSupport\Concerns\Support\HasCall;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Filesystem\Filesystem;
use Hanafalah\LaravelSupport\Supports\PathRegistry;

abstract class BaseCommand extends GeneratorCommand
{
    use CommandSupport\PromptList;
    use Concerns\ServiceProvider\HasConfiguration;
    use Concerns\DatabaseConfiguration\HasModelConfiguration;
    use ConcernsSupport\HasRepository;
    use ConcernsSupport\HasArray;
    use PromptLayout;
    use HasCall;

    protected PathRegistry $__registry_path;

    public function __construct(Filesystem $files, PathRegistry $paths)
    {
        parent::__construct($files);
        $this->__registry_path = $paths;
    }

    protected function resolvePath(string $key, string $name): string{
        $base = $this->__registry_path->get($key) ?? 'app';
        return base_path($base . '/' . str_replace('\\', '/', $name) . '.php');
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(){
        return function_exists('stub_path') ? stub_path() : base_path('stubs');
    }

    public function callCustomMethod()
    {
        return ['Model', 'Configuration'];
    }
}
