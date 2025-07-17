<?php

namespace Hanafalah\LaravelSupport;

// require 'vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Support\{
    Collection,
    Str
};
use Hanafalah\LaravelSupport\{
    Concerns,
    Contracts,
    Supports
};
use Composer\Autoload\ClassLoader;

class FileRepository implements Contracts\FileRepository
{
    use Concerns\Support\HasJson;

    /** @var Container */
    protected $__app;

    protected $__app_provider;

    /** @var string */
    protected $__path;

    /** @var array */
    protected $__config = [];

    protected $__items = [];

    /** @var array */
    protected $__service_providers = [];


    public function __construct(Container $app, ...$args)
    {
        $this->__app                    = $app;
        $this->__service_providers      = [];
        $this->__config                 = config('laravel-support');
        $this->__app->__file_repository = $this;
    }

    public function setupServices(): mixed
    {
        $conf_provider = 'laravel-support.class_discovering.provider';
        return $this->discoveringClass(config($conf_provider . '.paths'), config($conf_provider . '.base_classes'));
    }

    public function getVersioningJson()
    {
        return __DIR__ . '/versioning.json';
    }

    public function getContent($path): mixed
    {
        return \file_get_contents($path);
    }

    public function putContent($path, $data)
    {
        return file_put_contents($path, $data);
    }

    public function eachServices(array $paths, callable $callback): mixed
    {
        $results = [];
        foreach ($paths as $path) {
            if (!is_dir(\base_path($path))) mkdir($path, 0777, true);
            $value = $callback(base_path($path));
            if (isset($value)) $results[] = $value;
        }
        return $results;
    }

    public function renameVersion($class): string
    {
        $class = preg_replace('/(\d+)\.(\d+)/', 'V$1_$2', $class);
        return $class;
    }

    public function injectingLoader($class, $relativePath, $test = null): string
    {
        if (preg_match('/(\d+)\.(\d+)/', $class, $matches)) {
            if (!class_exists($class)) {
                $classes     = explode($matches[0], $class);
                $classLoader = new ClassLoader();

                $relative_paths = explode($matches[0], $relativePath);

                $classLoader->addPsr4($classes[0] . 'V' . \str_replace('.', '_', $matches[0]) . '\\', $relative_paths[0] . $matches[0] . '/');
                $classLoader->register();
                $class = $classes[0] . 'V' . \str_replace('.', '_', $matches[0]) . $classes[1];
            }
        }
        return $class;
    }

    public function discoveringClass($pathProviders, $baseClasses): Collection
    {
        return Supports\DiscoverClass::init($this->__app)->withPaths($pathProviders)->withBaseClasses($baseClasses)->discover();
    }

    //MUTATOR SECTION
    //SETTER

    public function setPath($path): self
    {
        $this->__path = $path;
        return $this;
    }

    //GETTER
    public function getClassReinforcement(string $path, ?string $basePath = null)
    {
        $class = trim(Str::replaceFirst($basePath ?? \base_path(), '', $path), DIRECTORY_SEPARATOR);
        $class = str_replace(
            [DIRECTORY_SEPARATOR, 'App\\'],
            ['\\', app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        );
        return $class;
    }


    public function getPath(): string
    {
        return $this->__path;
    }

    public function getRegisteredProviders(): array
    {
        return $this->__service_providers;
    }
    //END MUTATOR SECTION

}
