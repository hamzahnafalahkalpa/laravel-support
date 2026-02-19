<?php

namespace Hanafalah\LaravelSupport\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Hanafalah\LaravelSupport\Jobs\JobRequest;
use Hanafalah\LaravelSupport\Schemas\Elastic;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Queue\SerializesModels;

class ElasticJob implements ShouldQueue
{
    // use Queueable;
    use Queueable;

    public array $data;
    public $client;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        JobRequest::set($this->data);  
        if (config('elasticsearch.enabled', false) == false) {
            return;
        }
        $hosts = config('elasticsearch.hosts','localhost:9002');
        $this->client = ClientBuilder::create()->setHosts($hosts)
                        ->setApiKey(
                            config('app.elasticsearch.username',config('elasticsearch.username')),
                            config('app.elasticsearch.password',config('elasticsearch.password'))
                        )
                        ->build();  
        (new Elastic)->run($this->client,$this->data); 
    }
}
