<?php

namespace Hanafalah\LaravelSupport\Commands;

use Hanafalah\LaravelPackageGenerator\Concerns\HasGenerator;
use Hanafalah\LaravelSupport\{
    Commands\Concerns as CommandSupport,
    Concerns\Support as ConcernsSupport,
    Concerns
};
use Hanafalah\LaravelSupport\Concerns\Support\HasCall;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Filesystem\Filesystem;
use Hanafalah\LaravelSupport\Supports\PathRegistry;
use Illuminate\Support\Str;

abstract class BaseCommand extends GeneratorCommand
{
    use CommandSupport\PromptList;
    use Concerns\ServiceProvider\HasConfiguration;
    use Concerns\DatabaseConfiguration\HasModelConfiguration;
    use ConcernsSupport\HasRepository;
    use ConcernsSupport\HasArray;
    use HasGenerator;
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

    public function __callLibBasePath(){
        $method = $this->getCallMethod();
        if (Str::startsWith($method,'getBase') && Str::endsWith($method, 'Path')) {
            $lib = Str::between($method, 'getBase', 'Path');
            $base_main = $this->getBasePathFromMain();
            $path      = config($this->__snake_class_basename.'.libs.'.Str::lower($lib),$lib.'s');
            return $base_main.'/'.$path;
        }
    }

    public function callCustomMethod()
    {
        return ['Model', 'Configuration', 'LibBasePath'];
    }

    protected function getBasePathFromMain():string{
        $this->__snake_class_basename ??= Str::snake($this->__class_basename,'-');
        return dirname((new \ReflectionClass(app(config('app.contracts.'.$this->__class_basename))))->getFilename());
    }

    // protected function getBaseModelPath(): string{
    //     $base_main = $this->getBasePathFromMain();
    //     $path = config($this->__snake_class_basename.'.libs.model','Models');
    //     return $base_main.'/'.$path;
    // }
    
}
