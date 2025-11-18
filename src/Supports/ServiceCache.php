<?php

namespace Hanafalah\LaravelSupport\Supports;

use Hanafalah\LaravelSupport\Concerns\Support\HasCache;
use Hanafalah\LaravelSupport\Contracts\Supports\ServiceCache as SupportsServiceCache;

class ServiceCache implements SupportsServiceCache{
    use HasCache;

    protected $__cache_data = [
        'laravel-support' => [
            'name'    => 'app-laravel-support',
            'tags'    => ['laravel-support','app-laravel-support'],
            'forever' => true
        ]
    ];

    public function handle(?array $cache_data = null): void{
        $cache_data ??= $this->__cache_data['laravel-support'];
        $this->setCache($cache_data, function(){
            $cache = [
                'app.cached_lists' => [
                    'app.contracts',
                    'database.models',
                    'config-cache'
                ],
                'app.contracts'         => config('app.contracts'),
                'database.models'       => config('database.models')
            ];
            return $cache;
        }, false);
    }   

    public function getConfigCache(): ?array{
        $cache_data = $this->__cache_data['laravel-support'];
        $cache = $this->getCache($cache_data['name'],$cache_data['tags']);
        if (isset($cache)){
            config([
                'app.cached_lists' => $cache['app.cached_lists'] ?? [],
                'app.contracts'    => $cache['app.contracts'] ?? [],
                'database.models'  => $cache['database.models'] ?? []
            ]);
        }
        return $cache;
    }
}