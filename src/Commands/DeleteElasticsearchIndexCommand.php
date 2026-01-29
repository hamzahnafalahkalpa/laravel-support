<?php

namespace Hanafalah\LaravelSupport\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class DeleteElasticsearchIndexCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:delete-index
                            {model : The model class (e.g., "Patient" or "App\Models\Patient")}
                            {--flush : Only delete all documents, keep the index structure}
                            {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Elasticsearch index or flush all documents from an index';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $modelName = $this->argument('model');
        $flushOnly = $this->option('flush');
        $force = $this->option('force');

        // Find the model class
        $model = $this->findModelClass($modelName);

        if (!$model) {
            $this->error("Model '{$modelName}' not found!");
            return 1;
        }

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

        $indexName = $model->getElasticIndexName();

        try {
            $client = app('elasticsearch');

            // Check if index exists
            if (!$client->indices()->exists(['index' => $indexName])) {
                $this->warn("Index '{$indexName}' does not exist.");
                return 0;
            }

            // Get index stats before deletion
            $stats = $client->count(['index' => $indexName]);
            $docCount = $stats['count'] ?? 0;

            $this->newLine();
            $this->info("Index: {$indexName}");
            $this->info("Total documents: {$docCount}");
            $this->newLine();

            if ($flushOnly) {
                // Flush mode: delete all documents but keep the index
                if (!$force && !$this->confirm("Are you sure you want to delete all {$docCount} documents from '{$indexName}'?", false)) {
                    $this->info('Operation cancelled.');
                    return 0;
                }

                $this->info("Deleting all documents from index '{$indexName}'...");

                // Delete all documents using delete_by_query
                $response = $client->deleteByQuery([
                    'index' => $indexName,
                    'body' => [
                        'query' => [
                            'match_all' => new \stdClass()
                        ]
                    ]
                ]);

                $deleted = $response['deleted'] ?? 0;
                $this->newLine();
                $this->info("Successfully deleted {$deleted} documents from index '{$indexName}'.");
                $this->info("Index structure preserved.");

            } else {
                // Delete mode: delete the entire index
                if (!$force && !$this->confirm("Are you sure you want to DELETE the entire index '{$indexName}' with {$docCount} documents?", false)) {
                    $this->info('Operation cancelled.');
                    return 0;
                }

                $this->info("Deleting index '{$indexName}'...");

                $client->indices()->delete(['index' => $indexName]);

                $this->newLine();
                $this->info("Successfully deleted index '{$indexName}'.");
                $this->warn("The index structure has been removed.");
                $this->info("You may need to recreate the index before re-indexing data.");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to delete index: {$e->getMessage()}");
            $this->newLine();

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Find the model class from various formats
     *
     * @param string $modelName
     * @return Model|null
     */
    protected function findModelClass(string $modelName): ?Model
    {
        return $this->{$modelName.'Model'}();
    }
}
