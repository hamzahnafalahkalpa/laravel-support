<?php

namespace Hanafalah\LaravelSupport\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ElasticSearchObserver
{
    /**
     * Handle the Model "created" event.
     *
     * @param Model $model
     * @return void
     */
    public function created(Model $model): void
    {
        $this->indexModel($model);
    }

    /**
     * Handle the Model "updated" event.
     *
     * @param Model $model
     * @return void
     */
    public function updated(Model $model): void
    {
        $this->indexModel($model);
    }

    /**
     * Handle the Model "deleted" event.
     *
     * @param Model $model
     * @return void
     */
    public function deleted(Model $model): void
    {
        $this->deleteFromIndex($model);
    }

    /**
     * Index a model in Elasticsearch
     *
     * @param Model $model
     * @return void
     */
    protected function indexModel(Model $model): void
    {
        // Check if auto-indexing is enabled
        if (!config('elasticsearch.auto_index.enabled', true)) {
            return;
        }

        // Check if model has Elasticsearch enabled
        if (!method_exists($model, 'isElasticSearchEnabled') || !$model->isElasticSearchEnabled()) {
            return;
        }

        try {
            // Get searchable data
            $data = method_exists($model, 'toElasticArray')
                ? $model->toElasticArray()
                : $this->extractSearchableData($model);

            // Get index name
            $indexName = method_exists($model, 'getElasticIndexName')
                ? $model->getElasticIndexName()
                : $model->getTable();

            // Dispatch ElasticJob
            $jobClass = config('elasticsearch.job_class', 'App\Jobs\ElasticJob');

            if (!class_exists($jobClass)) {
                // Try wellmed-gateway location
                $jobClass = '\WellmedGateway\Jobs\ElasticJob';
            }

            if (class_exists($jobClass)) {
                dispatch(new $jobClass([
                    'type' => 'BULK',
                    'datas' => [[
                        'index' => $indexName,
                        'action' => 'index',
                        'data' => [$data]
                    ]]
                ]))
                    ->onQueue(config('elasticsearch.auto_index.queue', 'elasticsearch'))
                    ->onConnection(config('elasticsearch.auto_index.connection', 'rabbitmq'));
            } else {
                Log::warning('ElasticJob class not found for auto-indexing', [
                    'model' => get_class($model),
                    'tried_classes' => ['App\Jobs\ElasticJob', 'WellmedGateway\Jobs\ElasticJob']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to index model in Elasticsearch', [
                'error' => $e->getMessage(),
                'model' => get_class($model),
                'id' => $model->getKey()
            ]);
        }
    }

    /**
     * Delete a model from Elasticsearch index
     *
     * @param Model $model
     * @return void
     */
    protected function deleteFromIndex(Model $model): void
    {
        // Check if auto-indexing is enabled
        if (!config('elasticsearch.auto_index.enabled', true)) {
            return;
        }

        // Check if model has Elasticsearch enabled
        if (!method_exists($model, 'isElasticSearchEnabled') || !$model->isElasticSearchEnabled()) {
            return;
        }

        try {
            // Get index name
            $indexName = method_exists($model, 'getElasticIndexName')
                ? $model->getElasticIndexName()
                : $model->getTable();

            // Dispatch ElasticJob for deletion
            $jobClass = config('elasticsearch.job_class', 'App\Jobs\ElasticJob');

            if (!class_exists($jobClass)) {
                // Try wellmed-gateway location
                $jobClass = '\WellmedGateway\Jobs\ElasticJob';
            }

            if (class_exists($jobClass)) {
                dispatch(new $jobClass([
                    'type' => 'DELETE',
                    'datas' => [[
                        'index' => $indexName,
                        'id' => $model->getKey()
                    ]]
                ]))
                    ->onQueue(config('elasticsearch.auto_index.queue', 'elasticsearch'))
                    ->onConnection(config('elasticsearch.auto_index.connection', 'rabbitmq'));
            }

        } catch (\Exception $e) {
            Log::error('Failed to delete model from Elasticsearch', [
                'error' => $e->getMessage(),
                'model' => get_class($model),
                'id' => $model->getKey()
            ]);
        }
    }

    /**
     * Extract searchable data from model
     *
     * @param Model $model
     * @return array
     */
    protected function extractSearchableData(Model $model): array
    {
        $data = ['id' => $model->getKey()];

        // Get searchable fields
        if (method_exists($model, 'getElasticSearchableFields')) {
            $fields = $model->getElasticSearchableFields();
            foreach ($fields as $field) {
                $data[$field] = $model->$field;
            }
        } else {
            // Fallback: use all fillable fields
            $fillable = $model->getFillable();
            foreach ($fillable as $field) {
                if (!in_array($field, ['props', 'created_at', 'updated_at', 'deleted_at'])) {
                    $data[$field] = $model->$field;
                }
            }
        }

        return $data;
    }
}
