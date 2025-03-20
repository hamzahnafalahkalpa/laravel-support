<?php

use Illuminate\Support\ServiceProvider;

use Zahzah\LaravelSupport\{
    Models, Commands, Contracts
};

return [
    'stub'    => [
        /*
        |--------------------------------------------------------------------------
        | Overide zahzah/laravel-stub
        |--------------------------------------------------------------------------
        |
        | We override the config from "zahzah/laravel-stub"
        | to customize the stubs for our needs.
        |
        */
        'open_separator'  => '{{',
        'close_separator' => '}}',
        'path'            => stub_path(),
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
        'enabled' => env('USING_CACHE',false)
    ],
    'contracts'     => [
        'response'         => Contracts\Response::class,
        'laravel_support'  => Contracts\LaravelSupport::class
    ],
    'database'      => [
        'scope'     => [
            'paths' => [
                'App/Scopes'
            ]
        ],
        'models'  => [       
            'Activity'          => Models\Activity\Activity::class,
            'ActivityStatus'    => Models\Activity\ActivityStatus::class,
            'LogHistory'        => Models\LogHistory\LogHistory::class,     
            'ModelHasRelation'  => Models\Relation\ModelHasRelation::class,
            'PayloadMonitoring' => Models\PayloadMonitoring\PayloadMonitoring::class,
            'ModelHasPhone'     => Models\Phone\ModelHasPhone::class,
            'Encoding'          => Models\Encoding\Encoding::class,
            'ModelHasEncoding'  => Models\Encoding\ModelHasEncoding::class,
            'ModelHasPhone'     => Models\Phone\ModelHasPhone::class,
            'ReportSummary'     => Models\ReportSummary\ReportSummary::class
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
        Commands\InstallMakeCommand::class
    ]
];