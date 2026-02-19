<?php

namespace Hanafalah\LaravelSupport\Schemas;

use Hanafalah\LaravelSupport\Concerns\Support\HasElasticsearchLog;
use Hanafalah\LaravelSupport\Contracts\Schemas\ElasticBulk as SchemasElasticBulk;

class ElasticBulk extends Elastic implements SchemasElasticBulk {
    use HasElasticsearchLog;

    public function run($client,?array $attributes = null){
        $bulks = [];
        foreach ($attributes['datas'] as $datas) {
            $action = $datas['action'] ?? 'index';

            if ($action === 'delete') {
                // Handle delete action
                $bulks[] = [
                    'delete' => [
                        '_index' => $datas['index'],
                        '_id' => $datas['id']
                    ]
                ];
            } else {
                // Handle index action (default)
                foreach ($datas['data'] as $data) {
                    $bulks[] = [
                        'index' => [
                            '_index' => $datas['index'],
                            '_id' => $data['id']
                        ]
                    ];
                    $bulks[] = $data;
                }
            }
        }
        $this->bulks($bulks);
        $response = $client->bulk($this->__bulks);

        // Log ES operations to database
        if (config('elasticsearch.logging.enabled', true)) {
            $this->logElasticsearchOperations($this->__bulks['body'], $response->asArray());
        }
    }
}