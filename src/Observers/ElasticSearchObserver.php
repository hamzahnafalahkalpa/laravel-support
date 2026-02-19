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
        Log::debug('[ES Observer] Created event triggered', [
            'model' => get_class($model),
            'id' => $model->getKey()
        ]);
        $this->indexModel($model, 'created');
    }

    /**
     * Handle the Model "updated" event.
     *
     * @param Model $model
     * @return void
     */
    public function updated(Model $model): void
    {
        Log::debug('[ES Observer] Updated event triggered', [
            'model' => get_class($model),
            'id' => $model->getKey()
        ]);
        $this->indexModel($model, 'updated');
    }

    /**
     * Handle the Model "deleted" event.
     *
     * @param Model $model
     * @return void
     */
    public function deleted(Model $model): void
    {
        Log::debug('[ES Observer] Deleted event triggered', [
            'model' => get_class($model),
            'id' => $model->getKey()
        ]);
        $this->deleteFromIndex($model);
    }

    /**
     * Index a model in Elasticsearch
     *
     * @param Model $model
     * @param string $event
     * @return void
     */
    protected function indexModel(Model $model, string $event = 'unknown'): void
    {
        // Check if auto-indexing is enabled
        if (!config('elasticsearch.auto_index.enabled', true)) {
            Log::debug('[ES Observer] Auto-indexing is disabled');
            return;
        }

        // Check if model has Elasticsearch enabled
        if (!method_exists($model, 'isElasticSearchEnabled')) {
            Log::debug('[ES Observer] Model does not have isElasticSearchEnabled method', [
                'model' => get_class($model)
            ]);
            return;
        }

        if (!$model->isElasticSearchEnabled()) {
            Log::debug('[ES Observer] Elasticsearch is disabled for this model', [
                'model' => get_class($model)
            ]);
            return;
        }

        try {
            // IMPORTANT: Refresh model from database to ensure props are loaded
            // This is crucial for models using HasProps trait where virtual attributes
            // are stored in JSON props column
            if ($event === 'created' || $event === 'updated') {
                try {
                    $model->refresh();

                    // Additional step: Manually decode virtual columns if model uses HasProps
                    // The retrieved event (which normally decodes props) may not have fired yet
                    if (method_exists($model, 'decodeVirtualColumn')) {
                        $model->decodeVirtualColumn();
                        Log::debug('[ES Observer] Virtual columns decoded', [
                            'model' => get_class($model),
                            'id' => $model->getKey()
                        ]);
                    }

                    Log::debug('[ES Observer] Model refreshed from database', [
                        'model' => get_class($model),
                        'id' => $model->getKey()
                    ]);
                } catch (\Exception $e) {
                    Log::warning('[ES Observer] Failed to refresh model, using current state', [
                        'model' => get_class($model),
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Get searchable data
            // Check if model uses reporting context (checks elastic_config['use_reporting_index'])
            $useReporting = $model->elastic_config['use_reporting_index'] ?? false;
            $context = $useReporting ? 'reporting' : 'search';

            $data = method_exists($model, 'toElasticArray')
                ? $model->toElasticArray($context)
                : $this->extractSearchableData($model);

            // Get index name
            $indexName = method_exists($model, 'getElasticIndexName')
                ? $model->getElasticIndexName()
                : $model->getTable();

            Log::info('[ES Observer] Indexing model', [
                'event' => $event,
                'model' => get_class($model),
                'id' => $model->getKey(),
                'index' => $indexName,
                'data' => $data
            ]);

            // Dispatch ElasticJob
            $jobClass = config('elasticsearch.job_class', 'App\Jobs\ElasticJob');

            Log::debug('[ES Observer] Job class configured', [
                'job_class' => $jobClass,
                'exists' => class_exists($jobClass)
            ]);

            if (class_exists($jobClass)) {
                $jobPayload = [
                    'type' => 'BULK',
                    'datas' => [[
                        'index' => $indexName,
                        'action' => 'index',
                        'data' => [$data]
                    ]]
                ];

                $queue = config('elasticsearch.auto_index.queue', 'elasticsearch');
                $connection = config('elasticsearch.auto_index.connection', 'rabbitmq');

                Log::info('[ES Observer] Dispatching job', [
                    'job_class' => $jobClass,
                    'queue' => $queue,
                    'connection' => $connection,
                    'payload' => $jobPayload
                ]);

                dispatch(new $jobClass($jobPayload))
                    ->onQueue($queue)
                    ->onConnection($connection);

                Log::info('[ES Observer] Job dispatched successfully', [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                    'index' => $indexName
                ]);
            } else {
                Log::warning('[ES Observer] ElasticJob class not found', [
                    'model' => get_class($model),
                    'configured_class' => $jobClass
                ]);
            }

        } catch (\Exception $e) {
            Log::error('[ES Observer] Failed to index model', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            Log::debug('[ES Observer] Auto-indexing is disabled (delete)');
            return;
        }

        // Check if model has Elasticsearch enabled
        if (!method_exists($model, 'isElasticSearchEnabled') || !$model->isElasticSearchEnabled()) {
            Log::debug('[ES Observer] Elasticsearch not enabled for model (delete)', [
                'model' => get_class($model)
            ]);
            return;
        }

        try {
            // Get index name
            $indexName = method_exists($model, 'getElasticIndexName')
                ? $model->getElasticIndexName()
                : $model->getTable();

            Log::info('[ES Observer] Deleting from index', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'index' => $indexName
            ]);

            // Dispatch ElasticJob for deletion
            $jobClass = config('elasticsearch.job_class', 'App\Jobs\ElasticJob');

            if (!class_exists($jobClass)) {
                // Try wellmed-gateway location
                $jobClass = '\WellmedGateway\Jobs\ElasticJob';
            }

            if (class_exists($jobClass)) {
                $jobPayload = [
                    'type' => 'DELETE',
                    'datas' => [[
                        'index' => $indexName,
                        'id' => $model->getKey()
                    ]]
                ];

                dispatch(new $jobClass($jobPayload))
                    ->onQueue(config('elasticsearch.auto_index.queue', 'elasticsearch'))
                    ->onConnection(config('elasticsearch.auto_index.connection', 'rabbitmq'));

                Log::info('[ES Observer] Delete job dispatched', [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                    'index' => $indexName
                ]);
            }

        } catch (\Exception $e) {
            Log::error('[ES Observer] Failed to delete from Elasticsearch', [
                'error' => $e->getMessage(),
                'model' => get_class($model),
                'id' => $model->getKey()
            ]);
        }
    }

    /**
     * Extract searchable data from model
     *
     * This method extracts data using props query mapping for models with HasProps trait,
     * falling back to getAttribute() for regular attributes.
     *
     * @param Model $model
     * @return array
     */
    protected function extractSearchableData(Model $model): array
    {
        $data = ['id' => $model->getKey()];

        // Get props query mapping if available (for models using HasProps)
        $propsQueryMapping = method_exists($model, 'getPropsQuery') ? $model->getPropsQuery() : [];

        // Get searchable fields
        if (method_exists($model, 'getElasticSearchableFields')) {
            $fields = $model->getElasticSearchableFields();
            foreach ($fields as $field) {
                // Check if field has props query mapping
                if (isset($propsQueryMapping[$field])) {
                    $data[$field] = $this->extractValueFromPropsPath($model, $propsQueryMapping[$field]);
                } else {
                    $data[$field] = $model->getAttribute($field);
                }
            }
        } else {
            // Fallback: use all fillable fields
            $fillable = $model->getFillable();
            foreach ($fillable as $field) {
                if (!in_array($field, ['props', 'created_at', 'updated_at', 'deleted_at'])) {
                    if (isset($propsQueryMapping[$field])) {
                        $data[$field] = $this->extractValueFromPropsPath($model, $propsQueryMapping[$field]);
                    } else {
                        $data[$field] = $model->getAttribute($field);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Extract value from props path like 'props->prop_people->dob'
     *
     * @param Model $model
     * @param string $path Path like 'props->prop_people->dob'
     * @return mixed
     */
    protected function extractValueFromPropsPath(Model $model, string $path): mixed
    {
        // Remove 'props->' prefix if present
        $path = preg_replace('/^props->/', '', $path);

        // Split the path by '->'
        $segments = explode('->', $path);

        // Start from the first segment (e.g., 'prop_people')
        $value = $model->getAttribute($segments[0]);

        // Traverse nested paths
        for ($i = 1; $i < count($segments); $i++) {
            if (!is_array($value) || !isset($value[$segments[$i]])) {
                return null;
            }
            $value = $value[$segments[$i]];
        }

        return $value;
    }
}
