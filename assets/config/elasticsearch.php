<?php

use Hanafalah\LaravelSupport\Jobs\ElasticJob;

return [
    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable Elasticsearch globally. When disabled, all queries
    | will fall back to standard database queries.
    |
    */
    'enabled' => env('ELASTICSEARCH_ENABLED', true),

    'job_class' => ElasticJob::class,

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Hosts
    |--------------------------------------------------------------------------
    |
    | The Elasticsearch host(s) to connect to. Can be a single host or
    | comma-separated list of hosts.
    |
    */
    'hosts' => [env('ELASTICSEARCH_HOSTS', 'localhost:9200')],

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Credentials
    |--------------------------------------------------------------------------
    |
    | Username and password for Elasticsearch authentication.
    |
    */
    'username' => env('ELASTICSEARCH_USERNAME', 'elastic'),
    'password' => env('ELASTICSEARCH_PASSWORD', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Dynamic Index Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all Elasticsearch indexes. This can be set dynamically at
    | runtime (e.g., based on tenant ID) to support multi-tenancy.
    | Defaults to APP_ENV if not specified.
    |
    */
    'prefix' => env('ELASTICSEARCH_PREFIX', env('APP_ENV', 'development')),

    /*
    |--------------------------------------------------------------------------
    | Index Name Separator
    |--------------------------------------------------------------------------
    |
    | Character used to separate prefix from index name.
    | Example: prefix "tenant-001" + separator "." + index "patient" = "tenant-001.patient"
    |
    */
    'separator' => '.',

    /*
    |--------------------------------------------------------------------------
    | Query Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Elasticsearch query behavior.
    |
    */
    'query_timeout' => 5, // seconds

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | Automatically disable Elasticsearch after consecutive failures to prevent
    | cascading failures. The system will attempt to re-enable after cooldown.
    |
    */
    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5,
        'cooldown_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Indexing
    |--------------------------------------------------------------------------
    |
    | Automatically index model changes (create/update/delete) to Elasticsearch
    | using Laravel observers and queued jobs.
    |
    */
    'auto_index' => [
        'enabled' => true,
        'queue' => 'elasticsearch',
        'connection' => 'rabbitmq',
    ],
];
