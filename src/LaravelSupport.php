<?php

namespace Hanafalah\LaravelSupport;

use Illuminate\Container\Container;
use Hanafalah\LaravelSupport\Contracts\LaravelSupport as ContractsLaravelSupport;
use Hanafalah\LaravelSupport\Supports\PackageManagement;
use Stancl\JobPipeline\JobPipeline;
use Illuminate\Support\Facades\Event;

class LaravelSupport extends PackageManagement implements ContractsLaravelSupport
{
    protected $__app;
    public static $is_show_model = false;

    public function __construct(Container $app){
        $this->__app = $app;
    }

    public function showModeModel(?bool $is_show = true): self{
        self::$is_show_model = $is_show;
        return $this;
    }

    public function isShowModel(): bool{
        return self::$is_show_model;
    }

    public function callRoutes(string $path){
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $routes = array_diff(scandir($path), ['.', '..']);
        foreach ($routes as $route) {
            if (is_file($path . '/' . $route)) include_once($path . '/' . $route);
        }
    }

    public function eventPipelines(object $class){
        foreach ($class->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) $listener = $listener->toListener();
                Event::listen($event, $listener);
            }
        }
    }
}
