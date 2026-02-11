<?php

use Illuminate\Support\ServiceProvider;

use Hanafalah\LaravelSupport\{
    Models,
    Commands,
    Contracts,
    Supports
};

return [
    "namespace"     => "Hanafalah\LaravelSupport",
    'libs'    => [
        'model' => 'Models',
        'contract' => 'Contracts',
        'schema' => 'Schemas',
        'database' => 'Database',
        'data' => 'Data',
        'resource' => 'Resources',
        'migration' => '../assets/database/migrations'
    ],
    'config'    => [
        'path'  => config_path()
    ],
    'stub'      => [
        /*
        |--------------------------------------------------------------------------
        | Overide hanafalah/laravel-stub
        |--------------------------------------------------------------------------
        |
        | We override the config from "hanafalah/laravel-stub"
        | to customize the stubs for our needs.
        |
        */
        'open_separator'  => '{{',
        'close_separator' => '}}',
        'path'            => base_path('stubs'),
    ],
    'translate' => [
        'from'  => null, //default null to autodetect,
        'to'    => 'en'
    ],
    'service_cache' => Supports\ServiceCache::class,
    'payload_monitoring' => [
        'enabled' => true,
        'categories' => [
            'slow'    => 1000, // in miliseconds
            'medium'  => 500,
            'fast'    => 100
        ]
    ],
    'encoding_cache_data' => [
        'encoding' => [
            'name'     => 'encoding',
            'tags'     => ['encoding', 'encoding-index'],
            'duration' => 24 * 60
        ],
        'model_has_encoding' => [
            'name'     => 'model_has_encoding',
            'tags'     => ['encoding', 'model_has_encoding-index'],
            'duration' => 24 * 60
        ]
    ],
    'cache' => [
        'enabled' => env('USING_CACHE', false)
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Setup Cache
    |--------------------------------------------------------------------------
    |
    | Enable Redis-based caching for service provider setup (models, contracts).
    | This significantly improves performance by avoiding filesystem scanning
    | on every request in Octane environment.
    |
    | When enabled:
    | - Models and contracts are cached to Redis
    | - Cache is invalidated when composer.lock changes
    | - Use 'php artisan setup:cache {project}' to regenerate
    |
    */
    'use_redis_setup_cache' => env('USE_REDIS_SETUP_CACHE', false),

    'setup_cache' => [
        'ttl' => env('SETUP_CACHE_TTL', 604800), // 7 days in seconds
        'redis_connection' => env('SETUP_CACHE_REDIS_CONNECTION', 'setup'),
        'auto_generate' => env('SETUP_CACHE_AUTO_GENERATE', true), // Auto-generate cache on first request
    ],
    'app' => [
        'contracts'     => [
            //ADD YOUR CONTRACTS HERE
        ],
    ],
    'database'      => [
        'scope'     => [
            'paths' => [
                'App/Scopes'
            ]
        ],
        'models'  => [
        ]
    ],
    'class_discovering' => [
        'provider' => [
            'base_classes' => [ServiceProvider::class],
            'paths'        => []
        ],
        'model' => [
            'base_classes' => [],
            'paths'        => []
        ],
        //etc
    ],
    'commands' => [
        Commands\InstallMakeCommand::class,
        Commands\AddPackageCommand::class,
        Commands\SetupCacheCommand::class,
        // Commands\ElasticsearchIndexCommand::class,
        // Commands\GetElasticsearchIndexCommand::class
    ],
    // Add models from the desired namespaces to 'package_model_list' to keep track of providers
    'package_model_list' => null
];
