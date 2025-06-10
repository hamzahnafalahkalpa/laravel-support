<?php 

namespace Hanafalah\LaravelSupport\Supports;

class PathRegistry
{
    protected array $paths = [];

    public function set(string $key, string $path): void
    {
        $this->paths[$key] = $path;
    }

    public function get(string $key): ?string
    {
        return $this->paths[$key] ?? null;
    }

    public function all(): array
    {
        return $this->paths;
    }
}
