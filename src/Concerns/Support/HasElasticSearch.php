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
     * Get the Elasticsearch index name with prefix and tenant ID
     *
     * Format: {prefix}.{tenant_id}.{index_name} when tenant exists
     * Example: local.4.patient
     *
     * If the model implements getStaticIndexName(), it will be used directly
     * without tenant prefix. This is useful for shared/global data like
     * Disease, Province, District, Village, etc.
     *
     * @return string
     */
    public function getElasticIndexName(): string
    {
        // Check if model has static index name (no tenant prefix)
        if (method_exists($this, 'getStaticIndexName')) {
            return $this->getStaticIndexName();
        }

        $indexName = $this->elastic_config['index_name'] ?? $this->getTable();
        $prefix = config('elasticsearch.prefix', '');
        $separator = config('elasticsearch.separator', '.');

        // Build index name parts
        $parts = [];

        // Add prefix if exists
        if ($prefix) {
            $parts[] = $prefix;
        }

        // Add tenant ID only if not already in prefix
        $tenantId = $this->getElasticTenantId();
        if ($tenantId && !$this->prefixContainsTenantId($prefix, $tenantId, $separator)) {
            $parts[] = $tenantId;
        }

        // Add index name
        $parts[] = $indexName;

        return implode($separator, $parts);
    }

    /**
     * Check if the prefix already contains the tenant ID
     *
     * @param string $prefix
     * @param string $tenantId
     * @param string $separator
     * @return bool
     */
    protected function prefixContainsTenantId(string $prefix, string $tenantId, string $separator): bool
    {
        // Check if prefix ends with tenant ID (e.g., "local.4" ends with ".4")
        if (str_ends_with($prefix, $separator . $tenantId)) {
            return true;
        }

        // Check if prefix is exactly the tenant ID
        if ($prefix === $tenantId) {
            return true;
        }

        return false;
    }

    /**
     * Get the tenant ID for Elasticsearch index naming
     *
     * @return string|null
     */
    protected function getElasticTenantId(): ?string
    {
        // Check tenancy() helper first
        if (function_exists('tenancy') && tenancy()->tenant) {
            return (string) tenancy()->tenant->getKey();
        }

        // Fallback to config or session
        $tenantId = config('tenant.current_id') ?? session('tenant_id');

        return $tenantId ? (string) $tenantId : null;
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
     * @param string $operator 'and' or 'or'
     * @return array
     */
    public function buildElasticQuery(array $parameters, string $operator = 'and'): array
    {
        $clauses = [];
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

            // Skip non-string searches for date fields (e.g., searching "Udin" in dob)
            $castType = $casts[$field] ?? 'string';
            if (in_array($castType, ['date', 'datetime', 'timestamp', 'immutable_date', 'immutable_datetime']) && !$this->isValidDateValue($value)) {
                continue;
            }

            // Build query based on field type
            $fieldQuery = $this->buildElasticFieldQuery($field, $value, $castType);

            if ($fieldQuery) {
                $clauses[] = $fieldQuery;
            }
        }

        // If no clauses, return match_all
        if (empty($clauses)) {
            return [
                'query' => [
                    'match_all' => new \stdClass()
                ]
            ];
        }

        // Use 'should' (OR) or 'must' (AND) based on operator
        if (strtolower($operator) === 'or') {
            return [
                'query' => [
                    'bool' => [
                        'should' => $clauses,
                        'minimum_should_match' => 1
                    ]
                ]
            ];
        }

        return [
            'query' => [
                'bool' => [
                    'must' => $clauses
                ]
            ]
        ];
    }

    /**
     * Check if value is a valid date format
     *
     * @param mixed $value
     * @return bool
     */
    protected function isValidDateValue(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Check common date formats
        return (bool) strtotime($value);
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

        // Build Elasticsearch query with operator (and/or)
        $esQuery = $this->buildElasticQuery($parameters, $operator);

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
        $query->whereIn($this->getKeyName(), $result['ids']);

        // Maintain ES result order using database-specific syntax
        $driver = $query->getConnection()->getDriverName();
        $keyName = $this->getKeyName();

        if ($driver === 'pgsql') {
            // PostgreSQL: use array_position
            $quotedIds = array_map(fn($id) => "'" . addslashes($id) . "'", $result['ids']);
            $query->orderByRaw("array_position(ARRAY[" . implode(',', $quotedIds) . "]::text[], {$keyName}::text)");
        } else {
            // MySQL: use FIELD function
            $quotedIds = array_map(fn($id) => "'" . addslashes($id) . "'", $result['ids']);
            $query->orderByRaw("FIELD({$keyName}, " . implode(',', $quotedIds) . ")");
        }

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
