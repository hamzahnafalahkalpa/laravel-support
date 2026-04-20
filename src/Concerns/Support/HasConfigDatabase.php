<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasConfigDatabase
{
    use HasCall;

    private static $__config_base_path = 'database.models';
    private static $__config_resource_path = 'database.resources';
    private static array $__registered_resources = [];

    public function initializeHasConfigDatabase()
    {
        $this->mergeCasts($this->casts ?? []);
        if (isset($this->list) || isset($this->show)) {
            $this->mergeFillable($this->mergeArray($this->list ?? [], $this->show ?? []));
        }

        // Register resources once per model class
        $this->registerResourcesOnce();
    }

    /**
     * Register model resources to config once per model class.
     * This allows controllers to override resources via config().
     */
    protected function registerResourcesOnce(): void
    {
        $class = static::class;

        // Skip if already registered for this class
        if (isset(self::$__registered_resources[$class])) {
            return;
        }

        self::$__registered_resources[$class] = true;

        $modelName = $this->getMorphClass();
        $configKey = self::$__config_resource_path . '.' . $modelName;

        // Only register if not already set in config
        if (config($configKey) === null) {
            $viewResource = $this->getViewResource();
            $showResource = $this->getShowResource();

            if ($viewResource !== null || $showResource !== null) {
                $resources = [];
                if ($viewResource !== null) {
                    $resources['view'] = $viewResource;
                }
                if ($showResource !== null) {
                    $resources['show'] = $showResource;
                }
                config([$configKey => $resources]);
            }
        }
    }

    /**
     * Get the config key for this model's resources.
     * Override this method if you want a custom key.
     */
    // public function getResourceConfigKey(): string
    // {
        // return class_basename(static::class);
    // }

    /**
     * Resolve resource class from config or model method.
     * Config takes priority, allowing controller overrides.
     */
    protected function resolveResourceClass(string $type): ?string
    {
        $modelName = $this->getMorphClass();
        $basePath = self::$__config_resource_path . '.' . $modelName;

        // Check for specific type override first (e.g., database.resources.Patient.view)
        $specificConfig = config("{$basePath}.{$type}");
        if ($specificConfig !== null) {
            return $specificConfig;
        }

        // Check for general override (e.g., database.resources.Patient as string)
        $generalConfig = config($basePath);
        if ($generalConfig !== null && is_string($generalConfig)) {
            return $generalConfig;
        }

        // Fall back to model's method
        return $type === 'view' ? $this->getViewResource() : $this->getShowResource();
    }

    public function getShow()
    {
        return $this->show;
    }

    public function getList()
    {
        return $this->list;
    }

    public function getViewResource(){
        return null;
    }

    public function getShowResource(){
        return null;
    }

    public function toViewApi(){
        $resourceClass = $this->resolveResourceClass('view');

        return ($resourceClass !== null)
            ? new $resourceClass($this)
            : $this->toArray();
    }

    public function toViewApiExcepts(...$excludes): array{
        $excludes = $this->mustArray($excludes);
        $viewApi = $this->toViewAPi();
        if (!is_array($viewApi)) $viewApi = $viewApi->resolve();
        return $this->propExcludes($viewApi,...$excludes);
    }
    
    public function toShowApiExcepts(...$excludes): array{
        $excludes = $this->mustArray($excludes);
        $viewApi = $this->toShowApi();
        if (!is_array($viewApi)) $viewApi = $viewApi->resolve();
        return $this->propExcludes($viewApi,...$excludes);
    }

    public function toViewApiOnlies(...$onlies): array{
        $onlies = $this->mustArray($onlies);
        $viewApi = $this->toViewApi();
        if (!is_array($viewApi)) $viewApi = $viewApi->resolve();
        return $this->propOnlies($viewApi,...$onlies);
    }

    public function propOnlies(mixed $prop_attrs = [],...$onlies): array{
        if (!is_array($prop_attrs) && isset($prop_attrs)) $prop_attrs = $prop_attrs->resolve();
        return array_intersect_key($prop_attrs ?? [], array_flip($onlies ?? []));
    }

    public function propExcludes(mixed $prop_attrs = [],...$excludes): array{
        if (!is_array($prop_attrs) && isset($prop_attrs)) $prop_attrs = $prop_attrs->resolve();
        return array_diff_key($prop_attrs ?? [], array_flip($excludes ?? []));
    }

    public function propNil(mixed $prop_attrs = [],...$excludes): array{
        if (!is_array($prop_attrs) && isset($prop_attrs)) $prop_attrs = $prop_attrs->resolve();
        foreach($excludes as $key){            
            $prop_attrs[$key] = ($key == Str::plural($key)) ? [] : null;
        }
        return $prop_attrs;
    }

    public function toShowApi(){
        $resourceClass = $this->resolveResourceClass('show');

        return ($resourceClass !== null)
            ? new $resourceClass($this)
            : $this->toArray();
    }

    /**
     * Get the base path to the config of the model
     *
     * @return string
     */
    public function getConfigBaseModel(): string
    {
        return self::$__config_base_path;
    }

    public static function setConfigBaseModel(string $path): mixed
    {
        self::$__config_base_path = $path;
        return __CLASS__;
    }

    /**
     * Scope to filter query with search parameters
     *
     * HYBRID LOGIC SUPPORT:
     * When __explicit_search_fields metadata is present (from setParamLogic with search_value):
     * - Fields NOT in explicit list → OR group (from search_value expansion)
     * - Fields IN explicit list → AND group (explicit filters like status, created_at)
     * - Combines both groups: WHERE (OR(...)) AND (explicit_filter1) AND (explicit_filter2)
     *
     * @param $builder
     * @param string $operator 'and' or 'or'
     * @param mixed $parameters
     * @return mixed
     */
    public function scopeWithParameters($builder, string $operator = 'and', mixed $parameters = null)
    {
        $parameters ??= $this->filterArray(request()->all(), function ($key) {
            return Str::startsWith($key, 'search_');
        }, ARRAY_FILTER_USE_KEY);

        if (count($parameters) == 0) return $builder;

        // Check if we have explicit search fields metadata (hybrid mode)
        $explicitFieldsStr = $parameters['__explicit_search_fields'] ?? null;
        $explicitFields = $explicitFieldsStr ? explode(',', $explicitFieldsStr) : [];
        $hasExplicitFields = !empty($explicitFields);

        // Get connection info
        $connection_name = $this->getConnectionName();
        $connection      = config('database.connections.' . ($connection_name ?? config('database.default')));
        $db_driver       = $connection['driver'];
        $casts           = $this->getCasts();

        // Process parameters and separate into OR group and AND group
        $orParams = [];
        $andParams = [];

        foreach ($parameters as $key => $parameter) {
            // Skip metadata fields
            if ($key === '__explicit_search_fields') {
                continue;
            }

            if ($parameter == '' || !isset($parameter)) continue;

            // Parse field and operator from key
            $parsedKey = $this->parseSearchKey($key);
            $field = $parsedKey['field'];
            $searchOperator = $parsedKey['operator'];

            // Skip operator keys (they're handled in parseSearchKey)
            if ($field === null) {
                continue;
            }

            // Separate into OR and AND groups
            if ($hasExplicitFields && in_array($field, $explicitFields)) {
                // Explicit filter → AND group
                $andParams[$key] = [
                    'field' => $field,
                    'value' => $parameter,
                    'operator' => $searchOperator
                ];
            } else {
                // Search value expanded or normal → OR/AND group based on operator
                if ($hasExplicitFields) {
                    // In hybrid mode, non-explicit fields go to OR group
                    $orParams[$key] = [
                        'field' => $field,
                        'value' => $parameter,
                        'operator' => $searchOperator
                    ];
                } else {
                    // Standard mode: use operator to determine group
                    if (strtolower($operator) === 'or') {
                        $orParams[$key] = [
                            'field' => $field,
                            'value' => $parameter,
                            'operator' => $searchOperator
                        ];
                    } else {
                        $andParams[$key] = [
                            'field' => $field,
                            'value' => $parameter,
                            'operator' => $searchOperator
                        ];
                    }
                }
            }
        }

        // Build the query
        $builder->where(function ($query) use ($orParams, $andParams, $casts, $db_driver, $hasExplicitFields) {
            // Add OR group if exists
            if (!empty($orParams)) {
                $query->where(function ($orQuery) use ($orParams, $casts, $db_driver) {
                    foreach ($orParams as $key => $param) {
                        $field = $param['field'];
                        $parameter = $param['value'];
                        $searchOperator = $param['operator'];

                        $this->applyFieldCondition($orQuery, $field, $parameter, $searchOperator, 'or', $casts, $db_driver);
                    }
                });
            }

            // Add AND group
            foreach ($andParams as $key => $param) {
                $field = $param['field'];
                $parameter = $param['value'];
                $searchOperator = $param['operator'];

                $this->applyFieldCondition($query, $field, $parameter, $searchOperator, 'and', $casts, $db_driver);
            }
        });

        return $builder;
    }

    /**
     * Apply field condition to query based on cast type and operator
     *
     * @param $query
     * @param string $field
     * @param mixed $parameter
     * @param string|null $searchOperator
     * @param string $boolOperator 'and' or 'or'
     * @param array $casts
     * @param string $db_driver
     */
    protected function applyFieldCondition($query, string $field, $parameter, ?string $searchOperator, string $boolOperator, array $casts, string $db_driver): void
    {
        $query_field = (method_exists($this, 'getPropsQuery'))
            ? $this->getPropsQuery()[$field] ?? $field
            : $field;

        if (isset($casts[$field])) {
            switch ($casts[$field]) {
                case 'string':
                case 'text':
                    if (!in_array($query_field, $this->getFillable())) {
                        $query_field = str_replace('props->', '', $query_field);
                        switch ($db_driver) {
                            case 'pgsql':
                                $query_fields = explode('->', $query_field);
                                $query_field = array_map(function($item) {
                                    return "'".$item."'";
                                }, $query_fields);
                                $query_field = implode('->', $query_field);
                                $query_field = preg_replace('/->(?=[^>]*$)/', '->>', $query_field);
                                $query_field = DB::raw("LOWER(props->".$query_field .")");
                            break;
                            default:
                                $query_field = str_replace('->', '.', $query_field);
                                $query_field = DB::raw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(props, "$.' . $query_field . '")))');
                            break;
                        }
                    }else{
                        $query_field = DB::raw('LOWER(' . $query_field . ')');
                    }

                    $this->applyStringOperator($query, $query_field, $parameter, $searchOperator, $boolOperator);
                break;
                case 'array':
                    $query->whereNested(function ($query) use ($query_field, $parameter) {
                        $query->whereJsonContains($query_field, $parameter);
                    }, $boolOperator);
                break;
                case 'datetime':
                case 'date':
                case 'immutable_date':
                case 'immutable_datetime':
                    $this->applyDateOperator($query, $query_field, $parameter, $searchOperator, $boolOperator);
                break;
                case 'boolean':
                    $query->whereNested(function ($query) use ($query_field, $parameter) {
                        $query->where($query_field, (bool)$parameter);
                    }, $boolOperator);
                break;
                case 'integer':
                case 'float':
                case 'double':
                    $this->applyNumericOperator($query, $query_field, $parameter, $searchOperator, $boolOperator);
                break;
                default:
                    $query->whereNested(function ($query) use ($query_field, $parameter) {
                        $query->where($query_field, $parameter);
                    }, $boolOperator);
                break;
            }
        }
    }

    private function dateChecking(string|array $params)
    {
        $params = $this->mustArray($params);
        $is_date = true;
        foreach ($params as $param) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $param) && !preg_match('/^\d{4}-\d{2}$/', $param) && !preg_match('/^\d{4}$/', $param)) {
                $is_date = false;
                break;
            }
        }

        return $is_date;
    }

    /**
     * Convert date parameters from client timezone to UTC for database queries.
     *
     * This method is Octane-safe as it uses per-request timezone from middleware
     * instead of static variables.
     *
     * @param  string|array  $parameter
     * @return array
     */
    public function timezoneCalculation($parameter)
    {
        $results = [];
        $parameter = $this->mustArray($parameter);
        $clientTimezone = $this->getClientTimezone();
        $databaseTimezone = 'UTC';

        foreach ($parameter as $param) {
            // Date format: '2024-01-30'
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $param)) {
                // Parse date in client timezone and get full day range in UTC
                $date = Carbon::createFromFormat('Y-m-d', $param, $clientTimezone);
                $startOfDay = $date->copy()->startOfDay()->setTimezone($databaseTimezone);
                $endOfDay = $date->copy()->endOfDay()->setTimezone($databaseTimezone);
                $results[] = [$startOfDay->format('Y-m-d H:i:s'), $endOfDay->format('Y-m-d H:i:s')];
            }
            // DateTime format: '2024-01-30 14:30'
            elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $param)) {
                $date = Carbon::createFromFormat('Y-m-d H:i', $param, $clientTimezone);
                $results[] = $date->setTimezone($databaseTimezone)->format('Y-m-d H:i:s');
            }
            // DateTime with seconds: '2024-01-30 14:30:00'
            elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $param)) {
                $date = Carbon::parse($param, $clientTimezone);
                $results[] = $date->setTimezone($databaseTimezone)->format('Y-m-d H:i:s');
            }
            // Already processed or invalid, keep as is
            else {
                $results[] = $param;
            }
        }

        // If we have 2 date ranges, merge them into one range
        if (count($results) == 2 && is_array($results[0]) && is_array($results[1])) {
            return [[$results[0][0], $results[1][1]]];
        }

        return $results;
    }

    /**
     * Get the client timezone for the current request.
     *
     * @return string
     */
    protected function getClientTimezone(): string
    {
        if ($request = request()) {
            if ($timezone = $request->attributes->get('client_timezone')) {
                return $timezone;
            }
        }

        return date_default_timezone_get() ?: config('app.timezone', 'UTC');
    }

    /**
     * Generates the function comment for the given function.
     *
     * @param object $builder The builder object.
     * @param array $exceptions The exceptions array. Default is an empty array.
     * @throws None
     */
    public function scopeWithoutScopesExcepts($builder, $exceptions = [])
    {
        /** GET MODEL */
        $model = $builder->getModel();

        /** GET GLOBAL SCOPES */
        $globalScopes = $model->getGlobalScopes();

        /** FILTER SCOPES */
        $scopes = [];
        foreach ($globalScopes as $key => $scope) {
            $delete = true;
            if ($scope instanceof Scope) {
                foreach ($exceptions as $exc) {
                    foreach (config('database.scopes.paths') as $scope_path) {
                        if ($scope_path . $exc == $key) {
                            $delete = false;
                            break;
                        }
                    }
                    if (!$delete) break;
                }
            } else {
                if (in_array($key, $exceptions)) $delete = false;
            }
            if ($delete) $scopes[] = $key;
        }
        if (count($scopes) > 0) $builder->withoutGlobalScopes($scopes);
        $builder->scopeLists = $exceptions;
        return $builder;
    }


    public function morphToModel($related, $name, $type = null, $id = null, $localKey = null)
    {
        return $this->morphTo($this->{$related . 'ModelInstance'}(), $name, $type, $id, $localKey);
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne<TRelatedModel, $this>
     */
    public function morphOneModel($related, $name, $type = null, $id = null, $localKey = null)
    {
        $instance = $this->{$related . 'ModelInstance'}();
        $model    = app($instance);
        return $this->morphOne($instance, $name, $type, $id, $localKey);
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<TRelatedModel, $this>
     */
    public function morphManyModel($related, $name, $type = null, $id = null, $localKey = null)
    {
        $instance = $this->{$related . 'ModelInstance'}();
        $model    = app($instance);
        return $this->morphMany($instance, $name, $type, $id, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $ownerKey
     * @param  string|null  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<TRelatedModel, $this>
     */
    public function belongsToModel($related, $foreignKey = null, $ownerKey = null, $relation = null){
        $instance = $this->{$related . 'ModelInstance'}();
        $model    = app($instance);
        return $this->belongsTo($instance, $foreignKey ?? $model->getForeignKey(), $ownerKey, $relation);
    }

    /**
     * Define an inverse one-to-one or many JSON relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TRelatedModel> $related
     * @param string|array $foreignKey
     * @param string|array $ownerKey
     * @param string $relation
     * @return \Staudenmeir\EloquentJsonRelations\Relations\BelongsToJson<TRelatedModel, $this>
     */
    public function belongsToJsonModel($related, $foreignKey, $ownerKey = null, $relation = null){
        $related = $this->{$related . 'ModelInstance'}();
        return $this->belongsToJson($related, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TRelatedModel> $related
     * @param string|null $table
     * @param string|null $foreignKey
     * @param string|null $relatedKey
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<TRelatedModel, $this>
     */
    public function belongsToManyModel($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null)
    {
        $instance = $this->{$related . 'ModelInstance'}();
        $model = app($instance);

        // Check if $table is a string class, and if so, use the table name from the model instance
        if (is_string($table) && ($model_alias = config('database.models.' . $table)) !== null) {
            $model_alias = app($model_alias);
            $table = $model_alias->getTable();
        }

        return $this->belongsToMany(
            $instance,
            $table ?? $this->getBelongsToManyTable($related),
            $foreignPivotKey ?? $this->getForeignKey(),
            $relatedPivotKey ?? $model->getForeignKey(),
            $parentKey, $relatedKey, $relation
        );
    }

    protected function getBelongsToManyTable($related)
    {
        return Str::snake(class_basename($this)) . '_' . Str::snake(class_basename(app($this->{$related . 'ModelInstance'}())));
    }

    /**
     * Define a one-to-one relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<TRelatedModel, $this>
     */
    public function hasOneModel($related, $foreignKey = null, $localKey = null)
    {
        return $this->hasOne($this->{$related . 'ModelInstance'}(), $foreignKey ?? $this->getForeignKey(), $localKey);
    }

    public function hasManyThroughModel($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
    {
        $instance = $this->{$related . 'ModelInstance'}();
        $model    = app($instance);
        $through  = $this->{$through . 'ModelInstance'}();
        return $this->hasManyThrough($instance, $through, $firstKey ?? $model->getForeignKey(), $secondKey ?? $this->getForeignKey(), $localKey, $secondLocalKey);
    }


    public function hasOneThroughModel($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
    {
        $instance = $this->{$related . 'ModelInstance'}();
        $model    = app($instance);
        $through  = $this->{$through . 'ModelInstance'}();
        return $this->hasOneThrough($instance, $through, $firstKey ?? $model->getForeignKey(), $secondKey ?? $this->getForeignKey(), $localKey, $secondLocalKey);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<TRelatedModel, $this>
     */
    public function hasManyModel($related, $foreignKey = null, $localKey = null)
    {
    return $this->hasMany($this->{$related . 'ModelInstance'}(), $foreignKey ?? $this->getForeignKey(), $localKey);
    }

    // /**
    //  * Define a has-one-through relationship.
    //  *
    //  * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
    //  *
    //  * @param  class-string<TRelatedModel>  $related
    //  * @param  class-string<TRelatedModel>  $through
    //  * @param  string|null  $firstKey
    //  * @param  string|null  $secondKey
    //  * @param  string|null  $localKey
    //  * @param  string|null  $secondLocalKey
    //  * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough<TRelatedModel, $this>
    //  */
    // public function hasOneThroughModel($related,$through,$firstKey=null,$secondKey=null,$localKey=null,$secondLocalKey=null){
    //     return $this->hasOneThrough(
    //         $this->{$related.'ModelInstance'}(),$this->{$through.'ModelInstance'}(),
    //         $firstKey,$secondKey,$localKey,$secondLocalKey
    //     );
    // }

    /**
     * Parse search key to extract field and operator
     *
     * Supports multiple formats:
     * 1. search_field_name (uses default operator)
     * 2. search_field_name[operator] (operator in brackets)
     * 3. search_field_name with search_field_name_operator (separate operator parameter)
     *
     * @param string $key
     * @return array ['field' => string, 'operator' => string|null]
     */
    protected function parseSearchKey(string $key): array
    {
        $field = Str::after($key, 'search_');

        // Check if key ends with _operator pattern (skip these keys)
        if (preg_match('/^(.+)_operator$/', $field, $matches)) {
            return [
                'field' => null,
                'operator' => null
            ];
        }

        // Check if operator is specified: search_field[operator]
        if (preg_match('/^(.+)\[(.+)\]$/', $field, $matches)) {
            return [
                'field' => $matches[1],
                'operator' => $matches[2]
            ];
        }

        // Check if there's a corresponding operator key: search_field_name_operator
        $operatorKey = 'search_' . $field . '_operator';
        $allParams = request()->all();
        if (isset($allParams[$operatorKey])) {
            return [
                'field' => $field,
                'operator' => $allParams[$operatorKey]
            ];
        }

        return [
            'field' => $field,
            'operator' => null // Will use default operator based on type
        ];
    }

    /**
     * Apply string operator to query
     *
     * @param $query
     * @param $query_field
     * @param $parameter
     * @param string|null $searchOperator
     * @param string $operator
     */
    protected function applyStringOperator($query, $query_field, $parameter, ?string $searchOperator, string $operator): void
    {
        $searchOperator = $searchOperator ?? 'like'; // Default to LIKE for strings

        switch ($searchOperator) {
            case 'like':
                if (is_array($parameter)) {
                    foreach ($parameter as &$param) {
                        $param = Str::lower($param);
                    }
                    $query->whereNested(function ($query) use ($query_field, $parameter, $operator) {
                        foreach ($parameter as $param) {
                            if ($operator == 'or'){
                                $query->orWhereLike($query_field, "%$param%");
                            }else{
                                $query->whereLike($query_field, "%$param%");
                            }
                        }
                    }, $operator);
                } else {
                    $parameter = Str::lower($parameter);
                    $query->whereNested(function ($query) use ($query_field, $parameter) {
                        $query->whereLike($query_field, "%$parameter%")
                            ->orWhereLike($query_field, "$parameter%")
                            ->orWhereLike($query_field, "%$parameter")
                            ->orWhere($query_field, $parameter);
                    }, $operator);
                }
                break;

            case '=':
                $parameter = is_array($parameter) ? array_map([Str::class, 'lower'], $parameter) : Str::lower($parameter);
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    if (is_array($parameter)) {
                        $query->whereIn($query_field, $parameter);
                    } else {
                        $query->where($query_field, $parameter);
                    }
                }, $operator);
                break;

            case '!=':
                $parameter = is_array($parameter) ? array_map([Str::class, 'lower'], $parameter) : Str::lower($parameter);
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    if (is_array($parameter)) {
                        $query->whereNotIn($query_field, $parameter);
                    } else {
                        $query->where($query_field, '!=', $parameter);
                    }
                }, $operator);
                break;

            case 'in':
                $parameter = is_array($parameter) ? array_map([Str::class, 'lower'], $parameter) : [Str::lower($parameter)];
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $query->whereIn($query_field, $parameter);
                }, $operator);
                break;

            case 'not_in':
                $parameter = is_array($parameter) ? array_map([Str::class, 'lower'], $parameter) : [Str::lower($parameter)];
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $query->whereNotIn($query_field, $parameter);
                }, $operator);
                break;
        }
    }

    /**
     * Apply numeric operator to query
     *
     * @param $query
     * @param $query_field
     * @param $parameter
     * @param string|null $searchOperator
     * @param string $operator
     */
    protected function applyNumericOperator($query, $query_field, $parameter, ?string $searchOperator, string $operator): void
    {
        $searchOperator = $searchOperator ?? '='; // Default to = for numeric

        switch ($searchOperator) {
            case '=':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    if (is_array($parameter)) {
                        $query->whereIn($query_field, $parameter);
                    } else {
                        $query->where($query_field, '=', $parameter);
                    }
                }, $operator);
                break;

            case '!=':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    if (is_array($parameter)) {
                        $query->whereNotIn($query_field, $parameter);
                    } else {
                        $query->where($query_field, '!=', $parameter);
                    }
                }, $operator);
                break;

            case '>':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $query->where($query_field, '>', $parameter);
                }, $operator);
                break;

            case '<':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $query->where($query_field, '<', $parameter);
                }, $operator);
                break;

            case '>=':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $query->where($query_field, '>=', $parameter);
                }, $operator);
                break;

            case '<=':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $query->where($query_field, '<=', $parameter);
                }, $operator);
                break;

            case 'between':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $values = is_array($parameter) ? $parameter : explode(',', $parameter);
                    if (count($values) >= 2) {
                        $query->whereBetween($query_field, [$values[0], $values[1]]);
                    }
                }, $operator);
                break;

            case 'not_between':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $values = is_array($parameter) ? $parameter : explode(',', $parameter);
                    if (count($values) >= 2) {
                        $query->whereNotBetween($query_field, [$values[0], $values[1]]);
                    }
                }, $operator);
                break;

            case 'in':
                $parameter = is_array($parameter) ? $parameter : explode(',', $parameter);
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $query->whereIn($query_field, $parameter);
                }, $operator);
                break;

            case 'not_in':
                $parameter = is_array($parameter) ? $parameter : explode(',', $parameter);
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $query->whereNotIn($query_field, $parameter);
                }, $operator);
                break;
        }
    }

    /**
     * Apply date operator to query
     *
     * @param $query
     * @param $query_field
     * @param $parameter
     * @param string|null $searchOperator
     * @param string $operator
     */
    protected function applyDateOperator($query, $query_field, $parameter, ?string $searchOperator, string $operator): void
    {
        // Parse date range if string contains ' - '
        if (!is_array($parameter) && Str::contains($parameter, ' - ')) {
            $parameter = explode(' - ', $parameter);
        }

        $searchOperator = $searchOperator ?? 'between'; // Default to between for dates

        switch ($searchOperator) {
            case '=':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $parameter = $this->timezoneCalculation($parameter);
                    foreach ($parameter as $param) {
                        if (!is_array($param)) {
                            if ($this->dateChecking($param)) {
                                $query->where($query_field, $param);
                            }
                        } else {
                            $query->whereBetween($query_field, $param);
                        }
                    }
                }, $operator);
                break;

            case '!=':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $parameter = $this->timezoneCalculation($parameter);
                    foreach ($parameter as $param) {
                        if (!is_array($param)) {
                            if ($this->dateChecking($param)) {
                                $query->where($query_field, '!=', $param);
                            }
                        } else {
                            $query->whereNotBetween($query_field, $param);
                        }
                    }
                }, $operator);
                break;

            case '>':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $parameter = $this->timezoneCalculation($parameter);
                    $param = is_array($parameter) ? $parameter[0] : $parameter;
                    if (is_array($param)) {
                        $param = $param[1]; // Use end of day for > comparison
                    }
                    $query->where($query_field, '>', $param);
                }, $operator);
                break;

            case '<':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $parameter = $this->timezoneCalculation($parameter);
                    $param = is_array($parameter) ? $parameter[0] : $parameter;
                    if (is_array($param)) {
                        $param = $param[0]; // Use start of day for < comparison
                    }
                    $query->where($query_field, '<', $param);
                }, $operator);
                break;

            case '>=':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $parameter = $this->timezoneCalculation($parameter);
                    $param = is_array($parameter) ? $parameter[0] : $parameter;
                    if (is_array($param)) {
                        $param = $param[0]; // Use start of day for >= comparison
                    }
                    $query->where($query_field, '>=', $param);
                }, $operator);
                break;

            case '<=':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $parameter = $this->timezoneCalculation($parameter);
                    $param = is_array($parameter) ? $parameter[0] : $parameter;
                    if (is_array($param)) {
                        $param = $param[1]; // Use end of day for <= comparison
                    }
                    $query->where($query_field, '<=', $param);
                }, $operator);
                break;

            case 'between':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $parameter = $this->timezoneCalculation($parameter);
                    foreach ($parameter as $param) {
                        if (is_array($param)) {
                            $query->whereBetween($query_field, $param);
                        }
                    }
                }, $operator);
                break;

            case 'not_between':
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $parameter = $this->timezoneCalculation($parameter);
                    foreach ($parameter as $param) {
                        if (is_array($param)) {
                            $query->whereNotBetween($query_field, $param);
                        }
                    }
                }, $operator);
                break;

            case 'in':
                $parameter = is_array($parameter) ? $parameter : [$parameter];
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $query->whereIn($query_field, $parameter);
                }, $operator);
                break;

            case 'not_in':
                $parameter = is_array($parameter) ? $parameter : [$parameter];
                $query->whereNested(function ($query) use ($query_field, $parameter) {
                    $query->whereNotIn($query_field, $parameter);
                }, $operator);
                break;
        }
    }

    public function callCustomMethod(): array
    {
        return ['Model'];
    }
}
