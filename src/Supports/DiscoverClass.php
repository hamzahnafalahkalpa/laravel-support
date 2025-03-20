<?php

declare(strict_types=1);

namespace Hanafalah\LaravelSupport\Supports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;
use Illuminate\Container\Container;

class DiscoverClass
{
    /** @var Container */
    protected Container $__app;

    /** @var string[] */
    protected array $__paths = [];

    /** @var string[] */
    protected array $__baseClass = [];

    /** @var string[] */
    protected array $__ignoredClass = [];

    protected string $__basePath;

    protected string $__rootNamespace = '';

    public function __construct(Container $app)
    {
        $this->__basePath = base_path();
        $this->__app      = $app;
    }

    public static function init(Container $app): self
    {
        return new self($app);
    }

    public function withPaths(array $paths): self
    {
        $this->__paths = $paths;
        return $this;
    }

    public function withBaseClasses(array $baseClass): self
    {
        $this->__baseClass = $baseClass;
        return $this;
    }

    public function discover(): Collection
    {
        if (empty($this->__paths)) return collect();
        $files = (new Finder())->files()->in($this->__paths);
        $ignoredFiles = $this->getAutoloadedFiles(base_path('composer.json'));
        return collect($files)
            ->reject(fn(SplFileInfo $file) => in_array($file->getPathname(), $ignoredFiles))
            ->map(fn(SplFileInfo $file) => $this->nameSpaceFormFile($file))
            ->filter(fn(string $class) => $this->subOfClass($class))
            ->map(fn(string $class) => new ReflectionClass($class))
            ->reject(fn(ReflectionClass $reflection) => $reflection->isAbstract());
    }

    private function nameSpaceFormFile(SplFileInfo $file): string
    {
        $relativePath = Str::after($file->getRealPath(), base_path() . '\\');
        $class = trim(Str::replaceFirst($this->__basePath, '', $file->getRealPath()), DIRECTORY_SEPARATOR);
        $class = str_replace(
            [DIRECTORY_SEPARATOR, 'App\\'],
            ['\\', app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        );
        $class = $this->__app->__file_repository->injectingLoader($class, $relativePath);
        return $this->__rootNamespace . $class;
    }

    private function subOfClass(string $class): bool
    {
        if (in_array($class, $this->__ignoredClass)) return false;
        foreach ($this->__baseClass as $baseClass) {
            if (is_subclass_of($class, $baseClass))
                return (in_array(NowYouSeeMe::class, (new ReflectionClass($class))->getTraitNames())) ? true : false;
        }
        return false;
    }

    /**
     * Get the list of files which are autoloaded by composer.
     *
     * @param  string  $composerJsonPath
     * @return array
     */

    private function getAutoloadedFiles($composerJsonPath): array
    {
        if (! file_exists($composerJsonPath)) return [];
        $basePath         = Str::before($composerJsonPath, 'composer.json');
        $composerContents = json_decode(file_get_contents($composerJsonPath), true);
        $paths = array_merge(
            $composerContents['autoload']['files'] ?? [],
            $composerContents['autoload-dev']['files'] ?? []
        );
        return array_map(fn(string $path) => realpath($basePath . $path), $paths);
    }
}
