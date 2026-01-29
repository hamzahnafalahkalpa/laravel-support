<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait HasElasticSearch
{
    /**
     * Default Elasticsearch configuration
     *
     * @var array
     */
    protected array $elastic_config = [
        'enabled' => false,
        'index_name' => null,
        'variables' => [],
        'hydrate' => false,
    ];

    /**
     * Check if Elasticsearch is enabled globally and for this model
     *
     * @return bool
     */
    public function isElasticSearchEnabled(): bool
    {
        // Check global config
        if (!config('elasticsearch.enabled', false)) {
            return false;
        }
        // Check circuit breaker
        if ($this->isCircuitBreakerOpen()) {
            return false;
        }

        // Check model config
        return $this->elastic_config['enabled'] ?? false;
    }

    /**
     * Get the Elasticsearch index name with prefix
     *
     * @return string
     */
    public function getElasticIndexName(): string
    {
        $indexName = $this->elastic_config['index_name'] ?? $this->getTable();
        $prefix = config('elasticsearch.prefix', '');
        $separator = config('elasticsearch.separator', '.');

        return $prefix ? $prefix . $separator . $indexName : $indexName;
    }

    /**
     * Get fields that should be indexed in Elasticsearch
     *
     * @return array
     */
    public function getElasticSearchableFields(): array
    {
        // If variables explicitly configured, use those
        if (!empty($this->elastic_config['variables'])) {
            return $this->elastic_config['variables'];
        }

        // Otherwise, use all casted fields except excluded ones
        $casts = $this->getCasts();
        $excluded = ['props', 'created_at', 'updated_at', 'deleted_at'];

        return array_diff(array_keys($casts), $excluded);
    }

    /**
     * Build Elasticsearch query from search parameters
     *
     * @param array $parameters
     * @return array
     */
    public function buildElasticQuery(array $parameters): array
    {
        $must = [];
        $casts = $this->getCasts();

        foreach ($parameters as $key => $value) {
            // Only process search_* parameters
            if (!str_starts_with($key, 'search_')) {
                continue;
            }

            $field = substr($key, 7); // Remove 'search_' prefix

            // Skip if empty
            if (is_null($value) || $value === '') {
                continue;
            }

            // Build query based on field type
            $castType = $casts[$field] ?? 'string';
            $fieldQuery = $this->buildElasticFieldQuery($field, $value, $castType);

            if ($fieldQuery) {
                $must[] = $fieldQuery;
            }
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
     * Build Elasticsearch query for a specific field
     *
     * @param string $field
     * @param mixed $value
     * @param string $castType
     * @return array|null
     */
    protected function buildElasticFieldQuery(string $field, mixed $value, string $castType): ?array
    {
        switch ($castType) {
            case 'string':
            case 'text':
                // LIKE behavior using multi_match with phrase_prefix
                return [
                    'multi_match' => [
                        'query' => $value,
                        'fields' => [$field],
                        'type' => 'phrase_prefix'
                    ]
                ];

            case 'array':
            case 'json':
                // Array contains behavior
                if (is_array($value)) {
                    return [
                        'terms' => [
                            $field => $value
                        ]
                    ];
                }
                return [
                    'term' => [
                        $field => $value
                    ]
                ];

            case 'date':
            case 'datetime':
            case 'timestamp':
                // Support range queries for dates
                if (is_array($value)) {
                    $range = [];
                    if (isset($value['from'])) {
                        $range['gte'] = $value['from'];
                    }
                    if (isset($value['to'])) {
                        $range['lte'] = $value['to'];
                    }
                    return $range ? ['range' => [$field => $range]] : null;
                }
                // Exact date match
                return [
                    'term' => [
                        $field => $value
                    ]
                ];

            case 'boolean':
            case 'bool':
                // Boolean exact match
                return [
                    'term' => [
                        $field => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ]
                ];

            case 'integer':
            case 'int':
            case 'float':
            case 'double':
            case 'decimal':
                // Numeric exact match
                return [
                    'term' => [
                        $field => $value
                    ]
                ];

            default:
                // Fallback to term query
                return [
                    'term' => [
                        $field => $value
                    ]
                ];
        }
    }

    /**
     * Execute Elasticsearch query and return IDs
     *
     * @param array $esQuery
     * @param int $perPage
     * @param int $page
     * @param array $sort
     * @return array ['ids' => [], 'total' => 0]
     */
    public function executeElasticQuery(array $esQuery, int $perPage = 15, int $page = 1, array $sort = []): array
    {
        try {
            $client = app('elasticsearch');

            $params = [
                'index' => $this->getElasticIndexName(),
                'body' => $esQuery,
                'size' => $perPage,
                'from' => ($page - 1) * $perPage,
                '_source' => ['id'], // Only fetch IDs
            ];

            // Add sorting if provided
            if (!empty($sort)) {
                $params['body']['sort'] = $sort;
            }

            $response = $client->search($params);

            $ids = [];
            foreach ($response['hits']['hits'] as $hit) {
                $ids[] = $hit['_source']['id'];
            }

            $total = $response['hits']['total']['value'] ?? 0;

            // Reset circuit breaker on success
            $this->resetCircuitBreaker();

            return [
                'ids' => $ids,
                'total' => $total
            ];

        } catch (\Exception $e) {
            // Track failure
            $this->trackCircuitBreakerFailure();

            Log::warning('Elasticsearch query failed, falling back to database', [
                'error' => $e->getMessage(),
                'model' => get_class($this),
                'query' => $esQuery,
                'index' => $this->getElasticIndexName()
            ]);

            return [
                'ids' => [],
                'total' => 0,
                'error' => true
            ];
        }
    }

    /**
     * Query scope that filters by Elasticsearch results
     *
     * @param Builder $query
     * @param string $operator
     * @param mixed $parameters
     * @return Builder
     */
    public function scopeWithElasticSearch(Builder $query, string $operator = 'and', mixed $parameters = null): Builder
    {
        // Get parameters from request if not provided (same as withParameters)
        if ($parameters === null) {
            // Use filterArray if available (from HasArray trait), otherwise use array_filter
            if (method_exists($this, 'filterArray')) {
                $parameters = $this->filterArray(request()->all(), function ($key) {
                    return str_starts_with($key, 'search_');
                }, ARRAY_FILTER_USE_KEY);
            } else {
                $allParams = request()->all();
                $parameters = array_filter($allParams, function ($key) {
                    return str_starts_with($key, 'search_');
                }, ARRAY_FILTER_USE_KEY);
            }
        }

        // If no search parameters, return query as-is
        if (count($parameters) == 0) {
            return $query;
        }

        // Build Elasticsearch query
        $esQuery = $this->buildElasticQuery($parameters);

        // Get pagination parameters
        $perPage = (int) ($parameters['per-page'] ?? 15);
        $page = (int) ($parameters['page'] ?? 1);

        // Get sorting parameters
        $sort = [];
        if (isset($parameters['order-by'])) {
            $orderBy = $parameters['order-by'];
            $orderType = $parameters['order-type'] ?? 'asc';
            $sort = [
                $orderBy => [
                    'order' => strtolower($orderType)
                ]
            ];
        }

        // Execute Elasticsearch query
        $result = $this->executeElasticQuery($esQuery, $perPage, $page, $sort);

        // Store total for pagination
        $query->macro('getElasticTotal', function () use ($result) {
            return $result['total'];
        });

        $query->macro('getElasticError', function () use ($result) {
            return $result['error'] ?? false;
        });

        // If error occurred or no results, check if we should fallback
        if (isset($result['error']) && $result['error']) {
            // Return query as-is to fallback to database
            return $query;
        }

        // If no IDs, return empty result
        if (empty($result['ids'])) {
            $query->whereRaw('1 = 0'); // Force empty result
            return $query;
        }

        // Filter by IDs maintaining Elasticsearch order
        $query->whereIn($this->getKeyName(), $result['ids'])
            ->orderByRaw('FIELD(' . $this->getKeyName() . ', ' . implode(',', $result['ids']) . ')');

        return $query;
    }

    /**
     * Check if circuit breaker is open
     *
     * @return bool
     */
    protected function isCircuitBreakerOpen(): bool
    {
        if (!config('elasticsearch.circuit_breaker.enabled', true)) {
            return false;
        }

        $cacheKey = 'elastic_circuit_breaker:' . get_class($this);
        $failures = Cache::get($cacheKey, 0);
        $threshold = config('elasticsearch.circuit_breaker.failure_threshold', 5);

        return $failures >= $threshold;
    }

    /**
     * Track circuit breaker failure
     *
     * @return void
     */
    protected function trackCircuitBreakerFailure(): void
    {
        if (!config('elasticsearch.circuit_breaker.enabled', true)) {
            return;
        }

        $cacheKey = 'elastic_circuit_breaker:' . get_class($this);
        $cooldownMinutes = config('elasticsearch.circuit_breaker.cooldown_minutes', 5);

        $failures = Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $failures, now()->addMinutes($cooldownMinutes));

        if ($failures >= config('elasticsearch.circuit_breaker.failure_threshold', 5)) {
            Log::warning('Elasticsearch circuit breaker opened', [
                'model' => get_class($this),
                'failures' => $failures,
                'cooldown_minutes' => $cooldownMinutes
            ]);
        }
    }

    /**
     * Reset circuit breaker on successful query
     *
     * @return void
     */
    protected function resetCircuitBreaker(): void
    {
        if (!config('elasticsearch.circuit_breaker.enabled', true)) {
            return;
        }

        $cacheKey = 'elastic_circuit_breaker:' . get_class($this);
        Cache::forget($cacheKey);
    }

    /**
     * Get data for Elasticsearch indexing
     *
     * @return array
     */
    public function toElasticArray(): array
    {
        $searchableFields = $this->getElasticSearchableFields();
        $data = ['id' => $this->getKey()];

        foreach ($searchableFields as $field) {
            // Use getAttribute to properly handle props-based virtual attributes
            $value = $this->getAttribute($field);
            $data[$field] = $value;
        }

        return $data;
    }
}
