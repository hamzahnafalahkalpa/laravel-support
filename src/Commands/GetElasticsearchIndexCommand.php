<?php

namespace Hanafalah\LaravelSupport\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class GetElasticsearchIndexCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:get-index
                            {model : The model class to query (e.g., "Patient" or "App\Models\Patient")}
                            {--limit=10 : Number of records to display}
                            {--from=0 : Offset from which to start}
                            {--search=* : Search filters in format field:value}
                            {--raw : Display raw Elasticsearch response}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get and display data from Elasticsearch index';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $modelName = $this->argument('model');
        $limit = (int) $this->option('limit');
        $from = (int) $this->option('from');
        $searchFilters = $this->option('search');
        $raw = $this->option('raw');

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
        $this->info("Querying Elasticsearch index: {$indexName}");
        $this->newLine();

        try {
            $client = app('elasticsearch');

            // Build query
            $query = $this->buildQuery($searchFilters);

            $params = [
                'index' => $indexName,
                'body' => $query,
                'size' => $limit,
                'from' => $from,
            ];

            $response = $client->search($params);

            $total = $response['hits']['total']['value'] ?? 0;
            $hits = $response['hits']['hits'] ?? [];

            if ($raw) {
                // Display raw response
                $this->info("Raw Elasticsearch Response:");
                $this->line(json_encode($response, JSON_PRETTY_PRINT));
                return 0;
            }

            // Display formatted results
            $this->info("Total documents: {$total}");
            $this->info("Showing: " . count($hits) . " documents (offset: {$from})");
            $this->newLine();

            if (empty($hits)) {
                $this->warn("No documents found in the index.");
                return 0;
            }

            // Display documents in table format
            $this->displayDocuments($hits);

            $this->newLine();
            $this->info("Index: {$indexName}");
            $this->info("Query time: {$response['took']}ms");

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to query Elasticsearch: {$e->getMessage()}");
            $this->newLine();
            $this->warn("Make sure Elasticsearch is running and the index exists.");

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

    /**
     * Build Elasticsearch query from search filters
     *
     * @param array $searchFilters
     * @return array
     */
    protected function buildQuery(array $searchFilters): array
    {
        if (empty($searchFilters)) {
            return [
                'query' => [
                    'match_all' => new \stdClass()
                ]
            ];
        }

        $must = [];

        foreach ($searchFilters as $filter) {
            if (!str_contains($filter, ':')) {
                continue;
            }

            [$field, $value] = explode(':', $filter, 2);

            // Use wildcard query for flexible matching
            $must[] = [
                'wildcard' => [
                    $field => "*{$value}*"
                ]
            ];
        }

        return [
            'query' => [
                'bool' => [
                    'must' => $must ?: [['match_all' => new \stdClass()]]
                ]
            ]
        ];
    }

    /**
     * Display documents in table format
     *
     * @param array $hits
     * @return void
     */
    protected function displayDocuments(array $hits): void
    {
        foreach ($hits as $index => $hit) {
            $source = $hit['_source'] ?? [];
            $id = $hit['_id'] ?? 'N/A';
            $score = $hit['_score'] ?? 'N/A';

            $this->info("Document " . ($index + 1) . " (ID: {$id}, Score: {$score})");
            $this->table(
                ['Field', 'Value'],
                collect($source)->map(function ($value, $key) {
                    return [
                        $key,
                        is_array($value) ? json_encode($value) : (string) $value
                    ];
                })->toArray()
            );
            $this->newLine();
        }
    }
}
