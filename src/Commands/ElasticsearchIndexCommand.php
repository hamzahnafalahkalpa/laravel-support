<?php

namespace Hanafalah\LaravelSupport\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class ElasticsearchIndexCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:index
                            {model : The model class to index (e.g., "Patient" or "App\Models\Patient")}
                            {--chunk=100 : Number of records to process per batch}
                            {--from=0 : Start from this record ID}
                            {--limit=0 : Limit total records (0 = all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index existing database records into Elasticsearch';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $modelName = $this->argument('model');
        $chunkSize = (int) $this->option('chunk');
        $fromId = (int) $this->option('from');
        $limit = (int) $this->option('limit');

        // Find the model class
        $model = $this->findModelClass($modelName);

        if (!$model) {
            $this->error("Model '{$modelName}' not found!");
            return 1;
        }

        // $model = new $modelClass;

        // Check if Elasticsearch is enabled
        if (!method_exists($model, 'isElasticSearchEnabled')) {
            $this->error("Model '{$modelName}' does not support Elasticsearch!");
            $this->info("Make sure the model extends SupportBaseModel.");
            return 1;
        }
        if (!$model->isElasticSearchEnabled()) {
            $this->error("Elasticsearch is not enabled for model '{$modelName}'!");
            $this->info("Set \$elastic_config['enabled'] = true in the model.");
            return 1;
        }

        $this->info("Starting indexing for model: ".$model->getMorphClass);
        $this->info("Index: {$model->getElasticIndexName()}");
        $this->newLine();

        $query = $model::query();
        if ($fromId > 0) {
            $query->where($model->getKeyName(), '>=', $fromId);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }
        $totalRecords = $query->count();
        $this->info("Total records to index: {$totalRecords}");
        $this->newLine();
        if ($totalRecords === 0) {
            $this->warn("No records found to index.");
            return 0;
        }

        // Confirm before proceeding
        if (!$this->confirm('Do you want to proceed with indexing?', true)) {
            $this->info('Indexing cancelled.');
            return 0;
        }

        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();

        $indexed = 0;
        $failed = 0;

        // Process in chunks
        $query = $model::query();
        if ($fromId > 0) {
            $query->where($model->getKeyName(), '>=', $fromId);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }
        $query->chunk($chunkSize, function ($records) use ($model, &$indexed, &$failed, $bar) {
            $bulkData = [];

            foreach ($records as $record) {
                try {
                    // Get searchable data
                    $data = method_exists($record, 'toElasticArray')
                        ? $record->toElasticArray()
                        : $this->extractSearchableData($record);
                    $bulkData[] = $data;
                    $indexed++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("Failed to prepare record ID {$record->getKey()}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            // Dispatch bulk indexing job
            if (!empty($bulkData)) {
                $this->dispatchIndexJob($model, $bulkData);
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Indexing completed!");
        $this->info("Successfully queued: {$indexed}");
        if ($failed > 0) {
            $this->warn("Failed: {$failed}");
        }
        $this->newLine();
        $this->info("Jobs have been dispatched to the 'elasticsearch' queue.");
        $this->info("Make sure your queue worker is running: php artisan queue:work rabbitmq --queue=elasticsearch");

        return 0;
    }

    /**
     * Find the model class from various formats
     *
     * @param Model $modelName
     * @return Model|null
     */
    protected function findModelClass(string $modelName): ?Model
    {
        return $this->{$modelName.'Model'}();
        // // If already a full class name
        // if (class_exists($modelName)) {
        //     return $modelName;
        // }

        // // Try common namespaces
        // $namespaces = [
        //     'App\\Models\\',
        //     'Projects\\ModulePatient\\Models\\Patient\\',
        //     'Projects\\ModuleVisit\\Models\\Visit\\',
        //     'Projects\\ModuleBilling\\Models\\Billing\\',
        // ];

        // foreach ($namespaces as $namespace) {
        //     $class = $namespace . $modelName;
        //     if (class_exists($class)) {
        //         return $class;
        //     }
        // }

        // // Try to find in registered models
        // $registeredModels = config('database.models', []);
        // foreach ($registeredModels as $key => $class) {
        //     if (strtolower($key) === strtolower($modelName) || class_basename($class) === $modelName) {
        //         return $class;
        //     }
        // }

        // return null;
    }

    /**
     * Extract searchable data from model
     *
     * @param mixed $model
     * @return array
     */
    protected function extractSearchableData($model): array
    {
        $data = ['id' => $model->getKey()];

        if (method_exists($model, 'getElasticSearchableFields')) {
            $fields = $model->getElasticSearchableFields();
            foreach ($fields as $field) {
                // Use getAttribute to properly handle props-based virtual attributes
                $value = $model->getAttribute($field);
                $data[$field] = $value;
            }
        } else {
            // Fallback: use fillable
            $fillable = $model->getFillable();
            foreach ($fillable as $field) {
                if (!in_array($field, ['props', 'created_at', 'updated_at', 'deleted_at'])) {
                    $value = $model->getAttribute($field);
                    $data[$field] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * Dispatch indexing job
     *
     * @param mixed $model
     * @param array $bulkData
     * @return void
     */
    protected function dispatchIndexJob($model, array $bulkData): void
    {
        $jobClass = config('elasticsearch.job_class', 'App\Jobs\ElasticJob');

        if (!class_exists($jobClass)) {
            $jobClass = '\WellmedGateway\Jobs\ElasticJob';
        }

        if (class_exists($jobClass)) {
            dispatch(new $jobClass([
                'type' => 'BULK',
                'datas' => [[
                    'index' => $model->getElasticIndexName(),
                    'action' => 'index',
                    'data' => $bulkData
                ]]
            ]))
                ->onQueue(config('elasticsearch.auto_index.queue', 'elasticsearch'))
                ->onConnection('sync');
                // ->onConnection(config('elasticsearch.auto_index.connection', 'rabbitmq'));
        } else {
            $this->error('ElasticJob class not found!');
        }
    }
}
