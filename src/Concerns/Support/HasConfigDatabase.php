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
    public static $config_timezone, $config_client_timezone, $timezones;

    public function initializeHasConfigDatabase()
    {
        static::$config_timezone = config('app.timezone');
        static::$config_client_timezone = config('app.client_timezone', config('app.timezone'));
        static::$timezones = config('app.timezones', []);
        $this->mergeCasts($this->casts ?? []);
        if (isset($this->list) || isset($this->show)) {
            $this->mergeFillable($this->mergeArray($this->list ?? [], $this->show ?? []));
        }
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
        return ($this->getViewResource() !== null)
            ? new ($this->getViewResource())($this)
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
        return ($this->getShowResource() !== null)
            ? new ($this->getShowResource())($this)
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

    public function scopeWithParameters($builder, string $operator = 'and', mixed $parameters = null)
    {
        $parameters ??= $this->filterArray(request()->all(), function ($key) {
            return Str::startsWith($key, 'search_');
        }, ARRAY_FILTER_USE_KEY);

        if (count($parameters) == 0) return $builder;
        return $builder->where(function ($query) use ($parameters, $operator) {
            $connection_name = $this->getConnectionName();
            $connection      = config('database.connections.' . ($connection_name ?? config('database.default')));
            $db_driver       = $connection['driver'];
            foreach ($parameters as $key => $parameter) {
                if ($parameter == '') continue;
                $field = Str::after($key, 'search_');
                $casts = $this->getCasts();
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
                            if (is_array($parameter)){
                                foreach ($parameter as &$param) {
                                    $param = Str::lower($param);
                                }
                                $query->whereNested(function ($query) use ($query_field, $parameter) {
                                    $query->whereIn($query_field, $parameter);
                                }, $operator);
                            }else{
                                $parameter = Str::lower($parameter);
                                $query->whereNested(function ($query) use ($query_field, $parameter) {
                                    $query->whereLike($query_field, "%$parameter%")
                                        ->orWhereLike($query_field, "$parameter%")
                                        ->orWhereLike($query_field, "%$parameter")
                                        ->orWhere($query_field, $parameter);
                                }, $operator);
                            }
                        break;
                        case 'array':
                            $query->whereNested(function ($query) use ($query_field, $parameter) {
                                $query->whereJsonContains($query_field, $parameter);
                            }, $operator);
                        break;
                        case 'datetime':
                        case 'date':
                            if (!is_array($parameter)) {
                                if (Str::contains($parameter, ' - ')) {
                                    $parameter = explode(' - ', $parameter);
                                }
                            }
                            $query->whereNested(function ($query) use ($query_field, $parameter) {
                                $parameter = $this->timezoneCalculation($parameter);

                                foreach ($parameter as $param) {
                                    if (!is_array($param)) {
                                        if ($this->dateChecking($param)){
                                            $query->where($query_field, $param);
                                        }
                                    } else {
                                        if (!is_string($param[0]))
                                            $param[0] = $param[0];
                                        if (!is_string($param[1]))
                                            $param[1] = $param[1];
                                        $query->whereBetween($query_field, $param);
                                    }
                                }
                            }, $operator);
                        break;
                        case 'immutable_date':
                            if (!is_array($parameter)) {
                                if (Str::contains($parameter, ' - ')) {
                                    $parameter = explode(' - ', $parameter);
                                }
                            }
                            $query->whereNested(function ($query) use ($query_field, $parameter) {
                                $parameters = $this->mustArray($parameter);
                                if ($this->dateChecking($parameters)) {
                                    if (count($parameters) == 1) $parameters[1] = $parameters[0];
                                    $query->whereBetween($query_field, $parameters);
                                } else {
                                    $query->where($query_field, $parameters);
                                }
                            }, $operator);
                        break;
                        case 'immutable_datetime':
                            $query->whereNested(function ($query) use ($query_field, $parameter) {
                                if (is_array($parameter)) {
                                    $query->whereBetween($query_field, $parameter);
                                } else {
                                    $query->where($query_field, $parameter);
                                }
                            }, $operator);
                        break;
                        case 'boolean':
                            $query->whereNested(function ($query) use ($query_field, $parameter) {
                                $query->where($query_field, (bool)$parameter);
                            }, $operator);
                        break;
                        case 'integer':
                        case 'float':
                        case 'double':
                        default:
                            $query->whereNested(function ($query) use ($query_field, $parameter) {
                                $query->where($query_field, $parameter);
                            }, $operator);
                        break;
                    }
                } else {
                    // if (in_array($query_field, $this->getFillable())) {
                    //     $query->whereNested(function ($query) use ($query_field, $parameter) {
                    //         $query->where($query_field, $parameter);
                    //     }, $operator);
                    // }
                }
            }
        });
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

    public function timezoneCalculation($parameter)
    {
        if (isset(static::$config_client_timezone) && static::$config_timezone != static::$config_client_timezone) {
            $results   = [];
            if (in_array(static::$config_client_timezone, static::$timezones)) {
                $parameter = $this->mustArray($parameter);
                foreach ($parameter as $param) {
                    //CHECK IF $parameter IS DATE OR DATETIME
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $param)) {
                        $date       = Carbon::createFromFormat('Y-m-d', $param, static::$config_client_timezone);
                        $startOfDay = $date->copy()->startOfDay();
                        $endOfDay   = $date->copy()->endOfDay();
                        $startOfDay->setTimezone(static::$config_timezone);
                        $endOfDay->setTimezone(static::$config_timezone);
                        $results[] = [$startOfDay, $endOfDay];
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $param)) {
                        $date = Carbon::createFromFormat('Y-m-d H:i', $param, static::$config_client_timezone);
                        $results[] = $date->setTimezone(static::$config_timezone)->format('Y-m-d H:i');
                    } else {
                        $results[] = $param;
                    }
                }
                if (count($results) == 2) {
                    $results = [[$results[0][0], $results[1][1]]];
                }
            }
            return $results;
        }
        return [[$parameter, $parameter]];
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

    public function callCustomMethod(): array
    {
        return ['Model'];
    }
}
