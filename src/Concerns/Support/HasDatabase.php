<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Hanafalah\ModuleEncoding\Concerns\HasEncoding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasDatabase
{
  use HasArray, HasEncoding;

  protected static bool $__need_root = true;
  protected static array $__flags = [];

  public static function bootHasDatabase()
  {
    static::created(function ($query) {
      static::rootGenerator($query);
    });
    static::updated(function ($query) {
      static::rootGenerator($query);
    });

    // app()->booted(function(){
    //   if (!LaravelSupport::isShowModel()){
    //     static::addGlobalScope('list',function($query){
    //         $model = $query->getModel();
    //         if (count($model->list) > 0) {
    //             $lists = [];
    //             foreach ($model->list as $key) {
    //                 $lists[] = $model->getTable().'.'.$key;
    //             }
    //             $query->select($lists);                
    //         }
    //     });
    //   }else{
    //     static::addGlobalScope('show',function($query){
    //         $query->withoutGlobalScope('list')->show()->select('*');
    //     });
    //   }
    // });

    static::addGlobalScope('model_setup', function ($query) {
      $model = $query->getModel();
      $model->mergeFillable($model->mergeArray($model->list ?? [], $model->show ?? []));
      if (!$query->getQuery()->columns) {
        $query->select($model->getTable() . '.*');
      }
    });
  }

  /**
   * Retrieves the key name for the current instance.
   *
   * @param string|null $keyName The key name to retrieve. Defaults to null.
   * @return string The key name for the current instance.
   */
  public function getFieldName($fieldName = null)
  {
    $fieldName ??= $this->fieldName;
    return $this->{$fieldName};
  }

  public function getFieldStatus($fieldStatus = null)
  {
    $fieldStatus ??= $this->fieldStatus;
    return $this->{$fieldStatus};
  }

  public function getFieldFlag($fieldFlag = null)
  {
    $fieldFlag ??= $this->fieldFlag;
    return $this->{$fieldFlag};
  }

  public static function isSetUuid($query)
  {
    return in_array(static::getUuidName(), $query->getFillable());
  }

  public static function needRooting(): bool{
    return static::$__need_root;
  }

  //GETTER SECTION

  /**
   * Generates a new ID.
   *
   * @param int $n The length of the random string.
   * @param int $inc The amount of time to increment.
   * @return string The generated ID.
   */
  public function getNewId($n = null, $inc = 0): string
  {
    if (!isset($n)) $n = $this->lengthId ?? 26;
    return (\microtime() + $inc) . Str::random($n - 10);
  }

  public static function getParentId(): string
  {
    return 'parent_id';
  }

  public static function getGroupId(): string
  {
    return 'group_id';
  }

  public static function getUuidName()
  {
    return 'uuid';
  }

  public static function getRootName()
  {
    return 'root';
  }

  public function getDirtyRoot(): string
  {
    return 'parent_id';
  }

  //END GETTER SECTION

  //SETTER SECTION

  public static function setFlags(mixed $flags)
  {
    static::$__flags = is_array($flags) ? $flags : [$flags];
  }

  //END SETTER SECTION

  //MUTATOR
  /**
   * Generate root value based on parent_id and as_module
   *
   * @param self $query
   * @param mixed $dirties
   *
   * @return void
   */
  protected static function rootGenerator($query, mixed $dirties = null)
  {
    $dirties ??= $query->getDirtyRoot();
    if (static::isHasRoot($query) && static::needRooting()) {
      if (($query->wasRecentlyCreated && !isset($query->root)) || $query->isDirty($dirties)) {
        if (isset($query->parent_id)) {
          $parent = (new static)->find($query->parent_id);
          $rootId = $parent->root . "." . $query->id;
        }
        $query->root = $rootId ?? $query->id;
        $query->saveQuietly();
      }
    }
  }

  public static function isHasRoot($query)
  {
    return in_array(static::getRootName(), $query->getFillable());
  }

  /**
   * Validates if the given relation name is loaded and returns the result of the validation.
   *
   * @param string $relationName The name of the relation to be validated.
   * @param callable $callback The callback function to be executed when the validation is true.
   * @return mixed|null The result of the validation or null if the validation fails.
   */
  public function relationValidation($relationName, mixed $callback, mixed $catch = null)
  {
    $validation = $this->relationLoaded($relationName);
    if ($validation && isset($this->{$relationName})) {
      if (!is_callable($callback)) {
        $callback = function () use ($callback) {
          return $callback;
        };
      }
      return $callback();
    } else {
      if (isset($catch)){
        return is_callable($catch) ? $catch() : $catch;
      }else{
        return (Str::plural($relationName) == $relationName) ? [] : null;
      }
    }

    $validation = $this->relationLoaded($relationName);
    return $this->when($validation && isset($this->{$relationName}), function () use ($callback, $relationName) {
      if (!is_callable($callback)) {
        $callback = function () use ($callback) {
          return $callback;
        };
      }
      // if ($relationName == 'cardIdentities'){
      //   $value = $callback();
      //   if ($as_object && $value instanceof Collection){
      //     return (object) [];
      //   }
      // }
      return $callback();
      // return $this->when(true,$callback);
    });
    // if ($validation && isset($this->{$relationName})) {
    //   if (!is_callable($callback)){
    //     $callback = function() use ($callback){
    //       return $callback;
    //     };
    //   }
    //   if ($relationName == 'cardIdentities'){
    //     $value = $callback();
    //     if ($as_object && $value instanceof Collection){
    //       return (object) [];
    //     }
    //   }
    //   return $this->when(true,$callback);
    // }else{
    //   return $this->when(false);
    //   return null;
    // }
  }

  /**
   * Maps a collection of models to a new array with the specified keys and values.
   *
   * @param mixed $models The collection of models to be mapped.
   * @param mixed $key The key to be used in the resulting array. Can be a string or a callable.
   * @param mixed $value The value to be used in the resulting array. Can be a string or a callable.
   * @return array The resulting array with the specified keys and values.
   */
  public function mapWithKeys($models, $key, $value)
  {
    return (object) $models->mapWithKeys(function ($model) use ($key, $value) {
      $key   = (is_callable($key)) ? $key($model) : $model->{$key};
      $value = (is_callable($value)) ? $value($model) : $model->{$value};
      return [$key => $value];
    });
  }

  //LOCAL SECTION
  public function scopeShow($builder)
  {
    return $builder->withoutGlobalScope('list')->select($this->mergeArray($this->list ?? [], $this->show));
  }

  /**
   * Adds a where clause to the query builder to filter by a specific UUID.
   *
   * @param Builder $builder The query builder instance.
   * @param string $uuid The UUID value to filter by.
   * @param string $uuid_name The name of the UUID column. Defaults to "uuid".
   * @return Builder The query builder instance with the where clause applied.
   */
  public function scopeUUID($builder, $uuid, $uuid_name = "uuid")
  {
    return $builder->where($uuid_name, $uuid);
  }

  /**
   * A description of the entire PHP function.
   *
   * @param Builder $builder The query builder instance.
   * @param string $name The name of the column.
   * @param mixed $value 
   * @throws Some_Exception_Class A description of the exception that can be thrown.
   * @return Builder The query builder instance with the where clause applied.
   */
  public function scopeLT($builder, $name, $value)
  {
    //LESS THEN
    return $builder->where($name, '<', $value);
  }

  /**
   * A description of the entire PHP function.
   *
   * @param Builder $builder The query builder instance.
   * @param string $name The name of the column.
   * @param mixed $value 
   * @throws Some_Exception_Class A description of the exception that can be thrown.
   * @return Builder The query builder instance with the where clause applied.
   */
  public function scopeLTE($builder, $name, $value)
  {
    //LESS THEN EQUAL
    return $builder->where($name, '<=', $value);
  }

  /**
   * Add a "greater than" condition to the query.
   *
   * @param \Illuminate\Database\Eloquent\Builder $builder The query builder instance.
   * @param string $name The name of the column.
   * @param mixed $value The value to compare against.
   * @return Builder The query builder instance with the "greater than" condition applied.
   */
  public function scopeGT($builder, $name, $value)
  {
    //GREATER THEN
    return $builder->where($name, '>', $value);
  }

  /**
   * Add a "greater than or equal to" condition to the query.
   *
   * @param \Illuminate\Database\Eloquent\Builder $builder The query builder instance.
   * @param string $name The name of the column.
   * @param mixed $value The value to compare against.
   * @return \Illuminate\Database\Eloquent\Builder The query builder instance with the "greater than or equal to" condition applied.
   */
  public function scopeGTE($builder, $name, $value)
  {
    //GREATER THEN EQUAL
    return $builder->where($name, '>=', $value);
  }

  /**
   * Adds a "whereIn" clause to the query builder based on the given flags and flag name.
   *
   * @param \Illuminate\Database\Eloquent\Builder $builder The query builder instance.
   * @param mixed $flags The flags to search for. Can be a single value or an array of values.
   * @param string $flagName The name of the flag column. Defaults to "flag".
   * @return \Illuminate\Database\Eloquent\Builder The modified query builder instance.
   */
  public function scopeFlagIn($builder, $flags, $flagName = 'flag')
  {
    if (!is_array($flags)) $flags = [$flags];
    return $builder->whereIn($flagName, $flags);
  }

  /**
   * Adds a "whereIn" clause to the query builder based on the given flags and flag name.
   *
   * @param \Illuminate\Database\Eloquent\Builder $builder The query builder instance.
   * @param mixed $flags The flags to search for. Can be a single value or an array of values.
   * @param string $flagName The name of the flag column. Defaults to "flag".
   * @return \Illuminate\Database\Eloquent\Builder The modified query builder instance.
   */
  public function scopeCFlagIn($builder, $flags, $flagName = 'flag')
  {
    $newFlags = [];
    if (!is_array($flags)) $flags = [$flags];
    foreach ($flags as $flag) $newFlags[] = $this->constant($this, $flag);
    return $builder->whereIn($flagName, $newFlags);
  }

  /**
   * Adds conditional clauses to the query builder based on the given conditions.
   *
   * @param \Illuminate\Database\Eloquent\Builder $builder The query builder instance.
   * @param array $conditionals An array of conditions. Each condition can be a single value or an array of values.
   * @return \Illuminate\Database\Eloquent\Builder The modified query builder instance.
   */
  public function scopeConditionals($builder, mixed $conditionals = [])
  {
    $conditionals = $this->mustArray($conditionals ?? []);
    return $builder->when(isset($conditionals) && count($conditionals) > 0, function ($query) use ($conditionals) {
      $query->conditionalLoop($conditionals);
    });
  }

  /**
   * Iterates over an array of conditionals and applies them to a query builder.
   *
   * @param \Illuminate\Database\Eloquent\Builder $builder The query builder.
   * @param array $conditionals An array of conditionals.
   * @return \Illuminate\Database\Eloquent\Builder The query builder.
   */
  protected function scopeConditionalLoop($builder, $conditionals)
  {
    if (!isset($conditionals)) return $builder;
    foreach ($conditionals as $conditional) {
      if (!is_array($conditional)) $conditional = [true, $conditional];
      if (is_array($conditional) && count($conditional) == 1) $conditional = [true, $conditional[0]];
      if ($this->conditionalTemplate($conditional)) $builder->when($conditional[0], $conditional[1]);
    }
    return $builder;
  }

  /**
   * Check if the given conditional template is valid.
   *
   * @param array $conditional The conditional template to be checked.
   *                          The template should be an array with two elements:
   *                          - The first element should be a boolean value.
   *                          - The second element should be a callable function.
   * @return bool Returns true if the conditional template is valid, false otherwise.
   */
  private function conditionalTemplate($conditional)
  {
    return is_bool($conditional[0]) && is_callable($conditional[1]);
  }

  /**
   * Adds a "where not" condition to the query builder instance.
   *
   * @param \Illuminate\Database\Eloquent\Builder $builder The query builder instance.
   * @param string $flagName The name of the column.
   * @param mixed $value The value to search for.
   * @return \Illuminate\Database\Eloquent\Builder The modified query builder instance.
   */
  public function scopeWhereNot($builder, $flagName, $value)
  {
    return $builder->where($flagName, '<>', $value);
  }

  public function scopeWhereMorph($builder, $name, $class, $key = null)
  {
    return $builder->where(function ($q) use ($name, $class, $key) {
      if (\is_string($class)) $class = app($class);
      $q->where($name . '_type', $class->getMorphClass())
        ->where($name . '_id', $key ?? $class->getKey());
    });
  }

  /**
   * A description of the entire PHP function.
   *
   * @param Builder $builder The query builder instance.
   * @param string $name The name of the column.
   * @param mixed $val The value to search for.
   * @throws Some_Exception_Class A description of the exception that can be thrown.
   * @return Builder The query builder instance with the whereLike clause applied.
   */
  public function scopeWhereLike($builder, $name, $val)
  {
    if (strpos($val, '%') === false) $val = "%$val%";
    return $builder->where($name, "Like", $val);
  }

  /**
   * A description of the entire PHP function.
   *
   * @param Builder $builder The query builder instance.
   * @param string $name The name of the column.
   * @param mixed $val The value to search for.
   * @throws Some_Exception_Class A description of the exception that can be thrown.
   * @return Builder The query builder instance with the whereLike clause applied.
   */
  public function scopeOrWhereLike($builder, $name, $val)
  {
    if (strpos($val, '%') === false) $val = "%$val%";
    return $builder->orWhere($name, "Like", $val);
  }

  /**
   * A description of the entire PHP function.
   *
   * @param datatype $builder description
   * @param datatype $select description
   * @throws Some_Exception_Class description of exception
   * @return Some_Return_Value
   */
  public function scopeExclude($builder, $select = [])
  {
    return $builder->select($this->diff($builder->fillable, $select));
  }

  /**
   * Filters the query builder to only include records with the specified parent ID.
   *
   * @param Builder $builder The query builder instance.
   * @param int $id The parent ID to filter by.
   * @param string $parent_column The name of the parent column (default: 'parent_id').
   * @return Builder The modified query builder instance.
   */
  public function scopeParentId($builder, $id, $parent_column = 'parent_id')
  {
    return $builder->where($parent_column, $id);
  }

  /**
   * A function to filter the builder query to only include records from today.
   *
   * @param Builder $builder The query builder instance.
   * @param mixed $date The date column to compare with today.
   * @throws Exception If there is an error in the query.
   * @return Builder The modified query builder instance.
   */
  public function scopeOnlyToday($builder, $date)
  {
    return $builder->whereRaw("DATE_FORMAT(?,'%Y-%m-%d') = CURDATE()", $date);
  }

  /**
   * A description of the entire PHP function.
   *
   * @param datatype $builder description
   * @param datatype $flag description
   * @throws Some_Exception_Class description of exception
   * @return Some_Return_Value
   */
  public function scopeFlag($builder, $flag, $flagName = 'flag')
  {
    return $builder->flagIn($flagName, $flag, $flagName);
  }

  /**
   * A scope that finds records based on the given arguments.
   *
   * @param Builder $builder The query builder instance.
   * @param mixed $args The arguments used to find the records. Can be an array or a standalone variable.
   * @param mixed|null $val The value to compare with the standalone variable. Only required if $args is not an array.
   * @return Builder The query builder instance.
   */
  public function scopeFindBy($builder, $args, $val = null)
  {
    return (is_array($args))
      ? $builder->where($args)
      : $builder->where($args, $val);
  }
  //END LOCAL SCOPE SECTION

  //EIGER SECTION 

  public function parent()
  {
    return $this->belongsTo($this::class, static::getParentId(), $this->getKeyName());
  }

  public function groups()
  {
    return $this->hasMany(get_class($this), static::getGroupId());
  }

  public function childs()
  {
    return $this->hasMany(get_class($this), static::getParentId());
  }

  public function child()
  {
    return $this->hasOne(get_class($this), static::getParentId());
  }

  //END EIGER SECTION
}
