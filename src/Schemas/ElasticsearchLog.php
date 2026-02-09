<?php

namespace Hanafalah\LaravelSupport\Schemas;

use Hanafalah\LaravelSupport\Contracts\Data\ElasticsearchLogData;
use Hanafalah\LaravelSupport\Supports\PackageManagement;
use Hanafalah\LaravelSupport\Contracts\Schemas\ElasticsearchLog as ContractsElasticsearchLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ElasticsearchLog extends PackageManagement implements ContractsElasticsearchLog
{
    protected string $__entity = 'ElasticsearchLog';
    public $elasticsearch_log_model;
    protected mixed $__order_by_created_at = ['ordering','asc']; //asc, desc, false
    protected bool $__is_parent_only = true;

    protected array $__cache = [
        'index' => [
            'name'     => 'elasticsearch_log',
            'tags'     => ['elasticsearch_log', 'elasticsearch_log-index'],
            'forever'  => 24*7
        ]
    ];

    public function prepareStoreElasticsearchLog(ElasticsearchLogData $elasticsearch_log_dto): Model{            
        $add = [
            'name'      => $elasticsearch_log_dto->name,
            'reference_type' => $elasticsearch_log_dto->reference_type,
            'reference_id' => $elasticsearch_log_dto->reference_id,
            'synced_at' => $elasticsearch_log_dto->synced_at
        ];
        if (isset($elasticsearch_log_dto->id)){
            $guard  = ['id' => $elasticsearch_log_dto->id];
            $create = [$guard,$add];
        }else{
            $create = [$add];
        }
        $elasticsearch_log = $this->usingEntity()->withoutGlobalScopes()->updateOrCreate(...$create);
        $this->fillingProps($elasticsearch_log, $elasticsearch_log_dto->props);
        $elasticsearch_log->save();
        return $this->elasticsearch_log_model = $elasticsearch_log;
    }
}
