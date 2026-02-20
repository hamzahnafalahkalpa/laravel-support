<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Carbon\Carbon;
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
     * @param string $context 'search' or 'reporting'
     * @return array
     */
    public function getElasticSearchableFields(string $context = 'search'): array
    {
        // For reporting context, use reporting_variables if defined
        if ($context === 'reporting' && !empty($this->elastic_config['reporting_variables'])) {
            return $this->elastic_config['reporting_variables'];
        }

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
                // Convert date(s) to UTC range for Elasticsearch
                $utcRange = $this->convertDateToElasticUtcRange($value);

                if ($utcRange === null) {
                    return null;
                }

                return [
                    'range' => [
                        $field => $utcRange
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
     * Convert date parameter(s) to UTC range for Elasticsearch queries.
     *
     * Supports:
     * - Single date: '2026-02-02' -> range for entire day in UTC
     * - Array with two dates: ['2026-02-02', '2026-02-05'] -> range between dates in UTC
     * - Array with from/to keys: ['from' => '2026-02-02', 'to' => '2026-02-05']
     *
     * @param mixed $value
     * @return array|null ['gte' => '...', 'lte' => '...']
     */
    protected function convertDateToElasticUtcRange(mixed $value): ?array
    {
        $clientTimezone = $this->getElasticClientTimezone();
        $databaseTimezone = 'UTC';

        // Handle array input (range or from/to keys)
        if (is_array($value)) {
            // Check for from/to format
            if (isset($value['from']) || isset($value['to'])) {
                $range = [];
                if (isset($value['from'])) {
                    $startDate = $this->parseElasticDate($value['from'], $clientTimezone);
                    if ($startDate) {
                        $range['gte'] = $startDate->startOfDay()->setTimezone($databaseTimezone)->format('Y-m-d\TH:i:s\Z');
                    }
                }
                if (isset($value['to'])) {
                    $endDate = $this->parseElasticDate($value['to'], $clientTimezone);
                    if ($endDate) {
                        $range['lte'] = $endDate->endOfDay()->setTimezone($databaseTimezone)->format('Y-m-d\TH:i:s\Z');
                    }
                }
                return $range ?: null;
            }

            // Handle indexed array [date1, date2] for range
            if (count($value) >= 2) {
                $startDate = $this->parseElasticDate($value[0], $clientTimezone);
                $endDate = $this->parseElasticDate($value[1], $clientTimezone);

                if ($startDate && $endDate) {
                    return [
                        'gte' => $startDate->startOfDay()->setTimezone($databaseTimezone)->format('Y-m-d\TH:i:s\Z'),
                        'lte' => $endDate->endOfDay()->setTimezone($databaseTimezone)->format('Y-m-d\TH:i:s\Z'),
                    ];
                }
            }

            // Single element array - treat as single date
            if (count($value) === 1) {
                $value = $value[0];
            } else {
                return null;
            }
        }

        // Handle single date string - convert to full day range in UTC
        $date = $this->parseElasticDate($value, $clientTimezone);
        if (!$date) {
            return null;
        }

        return [
            'gte' => $date->copy()->startOfDay()->setTimezone($databaseTimezone)->format('Y-m-d\TH:i:s\Z'),
            'lte' => $date->copy()->endOfDay()->setTimezone($databaseTimezone)->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Parse a date string into a Carbon instance.
     *
     * @param string $value
     * @param string $timezone
     * @return Carbon|null
     */
    protected function parseElasticDate(string $value, string $timezone): ?Carbon
    {
        // Date format: '2024-01-30'
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value, $timezone);
        }

        // DateTime format: '2024-01-30 14:30'
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d H:i', $value, $timezone);
        }

        // DateTime with seconds: '2024-01-30 14:30:00'
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return Carbon::parse($value, $timezone);
        }

        // Try generic parsing
        try {
            return Carbon::parse($value, $timezone);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the client timezone for Elasticsearch queries.
     *
     * @return string
     */
    protected function getElasticClientTimezone(): string
    {
        if ($request = request()) {
            if ($timezone = $request->attributes->get('client_timezone')) {
                return $timezone;
            }
        }

        return date_default_timezone_get() ?: config('app.timezone', 'UTC');
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
            $startTime = microtime(true);

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

            $elapsed = round((microtime(true) - $startTime) * 1000, 2);

            // Log ES query timing if profiling enabled
            if (env('PATIENT_PROFILE', false)) {
                Log::info('[ESProfile] Query executed', [
                    'index' => $this->getElasticIndexName(),
                    'time_ms' => $elapsed,
                    'hits' => count($ids),
                    'total' => $total,
                    'query_type' => isset($esQuery['query']['bool']['should']) ? 'OR' : 'AND'
                ]);
            }

            return [
                'ids' => $ids,
                'total' => $total,
                'es_time_ms' => $elapsed
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
     * @param string $context 'search' or 'reporting' - determines which fields to include
     * @return array
     */
    public function toElasticArray(string $context = 'search'): array
    {
        $searchableFields = $this->getElasticSearchableFields($context);
        $data = ['id' => $this->getKey()];

        // Get props query mapping if available (for models using HasProps)
        $propsQueryMapping = method_exists($this, 'getPropsQuery') ? $this->getPropsQuery() : [];

        foreach ($searchableFields as $field) {
            $value = null;

            // Check if field has props query mapping (e.g., 'dob' => 'props->prop_people->dob')
            if (isset($propsQueryMapping[$field])) {
                $value = $this->extractValueFromPropsPath($propsQueryMapping[$field]);
            }

            // Fallback to getAttribute if props extraction didn't work
            if ($value === null) {
                $value = $this->getAttribute($field);
            }

            $data[$field] = $value;
        }

        return $data;
    }

    /**
     * Extract value from props path like 'props->prop_people->dob'
     *
     * @param string $path Path like 'props->prop_people->dob' or 'props->prop_people->card_identity->nik'
     * @return mixed
     */
    protected function extractValueFromPropsPath(string $path): mixed
    {
        // Remove 'props->' prefix if present
        $path = preg_replace('/^props->/', '', $path);

        // Split the path by '->'
        $segments = explode('->', $path);

        // Start from the first segment (e.g., 'prop_people')
        $value = $this->getAttribute($segments[0]);

        // Traverse nested paths
        for ($i = 1; $i < count($segments); $i++) {
            if (!is_array($value) || !isset($value[$segments[$i]])) {
                return null;
            }
            $value = $value[$segments[$i]];
        }

        return $value;
    }

    /**
     * Get data for reporting index (includes all reporting fields)
     * Alias for toElasticArray('reporting')
     *
     * @return array
     */
    public function toReportingArray(): array
    {
        return $this->toElasticArray('reporting');
    }

    /**
     * Get Elasticsearch document data for current model instance by ID
     * Model must be resolved and have an ID ($this->getKey() must not be null)
     *
     * @return array|null Returns document data or null if not found or error
     */
    public function getElasticDocument(): ?array
    {
        // Ensure model has an ID
        $id = $this->getKey();
        if (!$id) {
            Log::warning('Cannot get Elasticsearch document: Model ID is null', [
                'model' => get_class($this)
            ]);
            return null;
        }

        // Check if Elasticsearch is enabled
        if (!$this->isElasticSearchEnabled()) {
            return null;
        }

        try {
            $client = app('elasticsearch');

            $params = [
                'index' => $this->getElasticIndexName(),
                'id' => $id,
            ];

            $response = $client->get($params);

            // Return the document source data
            return $response['_source'] ?? null;

        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            // Document not found in Elasticsearch
            Log::info('Elasticsearch document not found', [
                'model' => get_class($this),
                'id' => $id,
                'index' => $this->getElasticIndexName()
            ]);
            return null;

        } catch (\Exception $e) {
            Log::warning('Failed to get Elasticsearch document', [
                'model' => get_class($this),
                'id' => $id,
                'index' => $this->getElasticIndexName(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get multiple Elasticsearch documents from the index
     *
     * This is a static-like method that can be called on model instance
     * to get multiple documents from Elasticsearch with pagination
     *
     * @param int $limit Number of documents to retrieve (default: 10)
     * @param int $offset Offset for pagination (default: 0)
     * @param array $query Optional Elasticsearch query (default: match_all)
     * @param array $sort Optional sort configuration
     * @return array Returns ['data' => [...], 'total' => int, 'limit' => int, 'offset' => int]
     */
    public function getElasticDocuments(int $limit = 10, int $offset = 0, array $query = [], array $sort = []): array
    {
        // Check if Elasticsearch is enabled
        if (!$this->isElasticSearchEnabled()) {
            return [
                'data' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => 'Elasticsearch is not enabled'
            ];
        }

        try {
            $client = app('elasticsearch');

            // Build query - use provided query or default to match_all
            $esQuery = !empty($query) ? $query : ['match_all' => new \stdClass()];

            $params = [
                'index' => $this->getElasticIndexName(),
                'body' => [
                    'query' => $esQuery,
                    'size' => $limit,
                    'from' => $offset,
                ],
            ];

            // Add sorting if provided
            if (!empty($sort)) {
                $params['body']['sort'] = $sort;
            }

            $response = $client->search($params);

            // Extract documents
            $documents = [];
            foreach ($response['hits']['hits'] as $hit) {
                $documents[] = $hit['_source'];
            }

            $total = $response['hits']['total']['value'] ?? 0;

            return [
                'data' => $documents,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ];

        } catch (\Exception $e) {
            Log::warning('Failed to get Elasticsearch documents', [
                'model' => get_class($this),
                'index' => $this->getElasticIndexName(),
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage()
            ]);

            return [
                'data' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage()
            ];
        }
    }
}
