<?php

namespace Hanafalah\LaravelSupport\Contracts\Schemas;

use Hanafalah\LaravelSupport\Contracts\Data\ElasticsearchLogData;
//use Hanafalah\LaravelSupport\Contracts\Data\ElasticsearchLogUpdateData;
use Hanafalah\LaravelSupport\Contracts\Supports\DataManagement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @see \Hanafalah\LaravelSupport\Schemas\ElasticsearchLog
 * @method mixed export(string $type)
 * @method self conditionals(mixed $conditionals)
 * @method array updateElasticsearchLog(?ElasticsearchLogData $elasticsearch_log_dto = null)
 * @method Model prepareUpdateElasticsearchLog(ElasticsearchLogData $elasticsearch_log_dto)
 * @method bool deleteElasticsearchLog()
 * @method bool prepareDeleteElasticsearchLog(? array $attributes = null)
 * @method mixed getElasticsearchLog()
 * @method ?Model prepareShowElasticsearchLog(?Model $model = null, ?array $attributes = null)
 * @method array showElasticsearchLog(?Model $model = null)
 * @method Collection prepareViewElasticsearchLogList()
 * @method array viewElasticsearchLogList()
 * @method LengthAwarePaginator prepareViewElasticsearchLogPaginate(PaginateData $paginate_dto)
 * @method array viewElasticsearchLogPaginate(?PaginateData $paginate_dto = null)
 * @method array storeElasticsearchLog(?ElasticsearchLogData $elasticsearch_log_dto = null)
 * @method Collection prepareStoreMultipleElasticsearchLog(array $datas)
 * @method array storeMultipleElasticsearchLog(array $datas)
 */

interface ElasticsearchLog extends DataManagement
{
    public function prepareStoreElasticsearchLog(ElasticsearchLogData $elasticsearch_log_dto): Model;
}