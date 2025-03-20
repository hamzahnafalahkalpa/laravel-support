<?php

namespace Zahzah\LaravelSupport\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

use Zahzah\LaravelSupport\Concerns\{
    DatabaseConfiguration as Database,
    ServiceProvider as SupportServiceProvider,
    Support
};

abstract class BaseRouteServiceProvider extends ServiceProvider{
    use Database\HasDatabaseConfiguration;
    use SupportServiceProvider\HasRouteConfiguration;
    use SupportServiceProvider\HasConfiguration;
    use Support\HasRepository; 
    
    abstract protected function dir(): string;

    public function __construct(Container $app) {
        parent::__construct($app);
        $this->__config = $app['config'];
    }
}