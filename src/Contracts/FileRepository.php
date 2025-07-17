<?php

namespace Hanafalah\LaravelSupport\Contracts;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;

interface FileRepository
{
    public function __construct(Container $app, ...$args);
    public function setupServices(): mixed;
    public function getContent($path): mixed;
    public function putContent($path, $data);
    public function eachServices(array $paths, callable $callback): mixed;
    public function renameVersion(string $class): string;
    public function injectingLoader(string $class, string $relativePath): string;
    public function discoveringClass($pathProviders, $baseClasses): Collection;
    public function setPath(string $path): self;
    public function getClassReinforcement(string $path, ?string $basePath = null);
    public function getPath(): string;
    public function getRegisteredProviders(): array;
}
