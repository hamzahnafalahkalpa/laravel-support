<?php

use Illuminate\Support\ServiceProvider;

use Hanafalah\LaravelSupport\{
    Models,
    Commands,
    Contracts
};

return [
    'libs'    => [
        'model' => 'Models',
        'contract' => 'Contracts'
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
    'payload_monitoring' => [
        'enabled' => true,
        'categories' => [
            'slow'    => 1000, // in miliseconds
            'medium'  => 500,
            'fast'    => 100
        ]
    ],
    'cache' => [
        'enabled' => env('USING_CACHE', false)
    ],
    'app' => [
        'contracts'     => [
            //ADD YOUR CONTRACTS HERE
            // 'response'         => Contracts\Response::class,
            // 'laravel_support'  => Contracts\LaravelSupport::class
        ],
    ],
    'database'      => [
        'scope'     => [
            'paths' => [
                'App/Scopes'
            ]
        ],
        'models'  => [
            // 'Activity'          => Models\Activity\Activity::class,
            // 'ActivityStatus'    => Models\Activity\ActivityStatus::class,
            // 'LogHistory'        => Models\LogHistory\LogHistory::class,
            // 'ModelHasRelation'  => Models\Relation\ModelHasRelation::class,
            // 'PayloadMonitoring' => Models\PayloadMonitoring\PayloadMonitoring::class,
            // 'ModelHasPhone'     => Models\Phone\ModelHasPhone::class,
            // 'Encoding'          => Models\Encoding\Encoding::class,
            // 'ModelHasEncoding'  => Models\Encoding\ModelHasEncoding::class,
            // 'ModelHasPhone'     => Models\Phone\ModelHasPhone::class,
            // 'ReportSummary'     => Models\ReportSummary\ReportSummary::class
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
        Commands\AddPackageCommand::class
    ],
    // Add models from the desired namespaces to 'package_model_list' to keep track of providers
    'package_model_list' => null
];
