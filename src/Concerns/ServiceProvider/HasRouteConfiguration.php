<?php

namespace Hanafalah\LaravelSupport\Concerns\ServiceProvider;

use Illuminate\Support\Facades\Route;
use Hanafalah\LaravelSupport\Concerns\Support\HasCall;
use Hanafalah\LaravelSupport\Concerns\Support\HasLocalDir;

trait HasRouteConfiguration
{
    use HasLocalDir;
    use HasCall;

    protected string $__route_base_path = '../assets/routes';

    protected function mergeRoutes(): self
    {
        foreach (['api', 'web'] as $name) {
            $path = $this->getRoutesFullPath($name);
            if ($this->isFile($path)) {
                $this->loadRoutesFrom($path);
            }
        }
        return $this;
    }

    protected function getRoutesFullPath(string $path): string
    {
        return $this->dir() . $this->getRouteBasePathResult() . "/$path.php";
    }

    private function registerRoute(string $prefix, array $middleware): \Illuminate\Routing\RouteRegistrar
    {
        return Route::prefix($prefix)->middleware($middleware);
    }

    protected function registerApiRoute(string $prefix = 'api', array $middleware = ['api']): \Illuminate\Routing\RouteRegistrar
    {
        return $this->registerRoute($prefix, $middleware);
    }

    protected function registerWebRoute(string $prefix = '', array $middleware = ['web']): \Illuminate\Routing\RouteRegistrar
    {
        return $this->registerRoute($prefix, $middleware);
    }
}
