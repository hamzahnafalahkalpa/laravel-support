<?php

namespace Hanafalah\LaravelSupport\Concerns\PackageManagement;

use Hanafalah\LaravelSupport\{
    Concerns\Support,
    Concerns\DatabaseConfiguration,
    Concerns\ServiceProvider,
    Concerns\PackageManagement as Package
};
use Hanafalah\LaravelSupport\Concerns\Support\RequestManipulation;
use Hanafalah\LaravelSupport\Contracts\Data\PaginateData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * OPTIMIZED VERSION of DataManagement trait
 *
 * Key optimizations:
 * 1. Cached entity name transformations (camel, snake)
 * 2. Cached method name mappings
 * 3. Cached config values
 * 4. Cached fillable arrays
 * 5. Reduced repeated method calls
 * 6. Optimized loops with array functions
 */
trait DataManagement
{
    private $__conditionals;
    protected mixed $__order_by_created_at = 'desc'; //asc, desc, false
    public static $param_logic = null;
    public $current_model;
    public $current_dto;

    // ============================================
    // OPTIMIZATION: Cached properties
    // ============================================
    protected ?string $__camel_entity = null;
    protected ?string $__snake_entity = null;
    protected ?array $__entity_methods = null;
    protected ?string $__entity_data_contract = null;
    protected ?Model $__cached_entity_model = null;
    protected static array $__fillable_cache = [];
    protected static array $__casts_cache = [];

    use RequestManipulation;
    use Support\HasRepository;
    use DatabaseConfiguration\HasModelConfiguration;
    use ServiceProvider\HasConfiguration;
    use Package\HasCallMethod;

    protected function methodHandler(string $method, array $methods)
    {
        $arguments = $this->getCallArguments() ?? [];
        if (isset($methods[$method])) {
            return $this->{$methods[$method]}(...$arguments);
        }
        return null;
    }

    public function __callSchemaEloquent()
    {
        $method = $this->getCallMethod();
        $entity = $this->getEntity();

        // OPTIMIZATION: Build method map once, reuse
        static $methodMapCache = [];
        $cacheKey = $entity;

        if (!isset($methodMapCache[$cacheKey])) {
            $methodMapCache[$cacheKey] = [
                "import"                        => 'generalImport',
                "export"                        => 'generalExport',
                "show$entity"                   => 'generalShow',
                "prepareShow$entity"            => 'generalPrepareShow',
                "view{$entity}Paginate"         => 'generalViewPaginate',
                "prepareView{$entity}Paginate"  => 'generalPrepareViewPaginate',
                "view{$entity}List"             => 'generalViewList',
                "prepareView{$entity}List"      => 'generalPrepareViewList',
                "find{$entity}"                 => 'generalFind',
                "prepareFind{$entity}"          => 'generalPrepareFind',
                "delete{$entity}"               => 'generalDelete',
                "prepareDelete{$entity}"        => 'generalPrepareDelete',
                "store{$entity}"                => 'generalStore',
                "storeMultiple{$entity}"        => 'generalStoreMultiple',
                "prepareStore"                  => 'generalUniversalPrepareStore',
                "prepareStore{$entity}"         => 'generalPrepareStore',
                "prepareStoreMultiple{$entity}" => 'generalPrepareStoreMultiple',
                "update{$entity}"               => 'generalUpdate',
                "prepareUpdate{$entity}"        => 'generalPrepareUpdate',
                Str::camel($entity)             => 'generalSchemaModel',
            ];
        }

        $result = $this->methodHandler($method, $methodMapCache[$cacheKey]);
        if (isset($result)) return $result;
    }

    public function getEntity(): string
    {
        return $this->__entity;
    }

    // ============================================
    // OPTIMIZATION: Cached entity name transformations
    // ============================================
    public function camelEntity(): string
    {
        return $this->__camel_entity ??= Str::camel($this->getEntity());
    }

    public function snakeEntity(): string
    {
        return $this->__snake_entity ??= Str::snake($this->getEntity());
    }

    // ============================================
    // OPTIMIZATION: Cached entity method names
    // ============================================
    protected function getEntityMethods(): array
    {
        if ($this->__entity_methods === null) {
            $entity = $this->getEntity();
            $this->__entity_methods = [
                'prepareStore'    => "prepareStore{$entity}",
                'show'            => "show{$entity}",
                'prepareShow'     => "prepareShow{$entity}",
                'viewList'        => "view{$entity}List",
                'prepareViewList' => "prepareView{$entity}List",
                'viewPaginate'    => "view{$entity}Paginate",
                'prepareViewPaginate' => "prepareView{$entity}Paginate",
                'find'            => "find{$entity}",
                'prepareFind'     => "prepareFind{$entity}",
                'delete'          => "delete{$entity}",
                'prepareDelete'   => "prepareDelete{$entity}",
                'update'          => "update{$entity}",
                'prepareUpdate'   => "prepareUpdate{$entity}",
                'storeMultiple'   => "storeMultiple{$entity}",
                'prepareStoreMultiple' => "prepareStoreMultiple{$entity}",
            ];
        }
        return $this->__entity_methods;
    }

    // ============================================
    // OPTIMIZATION: Cached config contracts
    // ============================================
    protected function getEntityDataContract(): ?string
    {
        return $this->__entity_data_contract ??= config("app.contracts.{$this->getEntity()}Data");
    }

    protected function getEntityUpdateDataContract(): ?string
    {
        static $cache = [];
        $entity = $this->getEntity();
        return $cache[$entity] ??= config("app.contracts.{$entity}UpdateData");
    }

    // ============================================
    // OPTIMIZATION: Cached fillable & casts
    // ============================================
    protected function getEntityFillable(): array
    {
        $entity = $this->getEntity();
        if (!isset(static::$__fillable_cache[$entity])) {
            static::$__fillable_cache[$entity] = $this->usingEntity()->getFillable();
        }
        return static::$__fillable_cache[$entity];
    }

    protected function getEntityCasts(): array
    {
        $entity = $this->getEntity();
        if (!isset(static::$__casts_cache[$entity])) {
            static::$__casts_cache[$entity] = array_keys($this->usingEntity()->getCasts());
        }
        return static::$__casts_cache[$entity];
    }

    /**
     * Add conditionals to the query
     */
    public function conditionals(mixed $conditionals): self
    {
        $this->__conditionals = $this->mergeCondition($conditionals ?? []);
        return $this;
    }

    public function mergeCondition(mixed $conditionals): array
    {
        $conditionals ??= [];
        $conditionals = $this->mustArray($conditionals);
        return $this->mergeArray($this->mustArray($this->__conditionals ?? []), $conditionals);
    }

    // OPTIMIZATION: Reduced double usingEntity() calls
    protected function viewUsingRelation(): array
    {
        $model = $this->usingEntity();
        return method_exists($model, 'viewUsingRelation') ? $model->viewUsingRelation() : [];
    }

    protected function showUsingRelation(): array
    {
        $model = $this->usingEntity();
        return method_exists($model, 'showUsingRelation') ? $model->showUsingRelation() : [];
    }

    public function usingEntity(): Model
    {
        return $this->{$this->getEntity() . 'Model'}();
    }

    public function entityData(mixed $model = null): mixed
    {
        $snakeEntity = $this->snakeEntity();
        if (!isset($model)) {
            return $this->{$snakeEntity . '_model'};
        } else {
            return $this->{$snakeEntity . '_model'} = $model;
        }
    }

    public function viewEntityResource(callable $callback, array $options = []): array
    {
        return $this->transforming($this->usingEntity()->getViewResource(), function () use ($callback) {
            return $callback();
        }, $options);
    }

    public function showEntityResource(callable $callback, array $options = []): array
    {
        return $this->transforming($this->usingEntity()->getShowResource(), function () use ($callback) {
            return $callback();
        }, $options);
    }

    public function autolist(?string $response = 'list', ?callable $callback = null): mixed
    {
        if (isset($callback)) $this->conditionals($callback);

        $request = request();
        if (isset($request->search_except_id)) {
            $exceptId = $request->search_except_id;
            $this->conditionals(function ($query) use ($exceptId) {
                $query->where('id', '!=', $exceptId);
            });
        }

        $methods = $this->getEntityMethods();

        switch ($response) {
            case 'list':
                return $this->{$methods['viewList']}();
            case 'paginate':
                return $this->{$methods['viewPaginate']}();
            case 'find':
                return $this->{$methods['find']}();
        }
        abort(404);
    }

    public function generalExport(string $type): mixed
    {
        if (!isset($this->__config_name)) throw new \Exception('No config name provided', 422);
        $type = Str::studly($type);
        $export_class = config($this->__config_name . '.exports.' . $type, null);
        return new $export_class($this);
    }

    public function generalImport(string $type): mixed
    {
        if (!isset($this->__config_name)) throw new \Exception('No config name provided', 422);
        $type = Str::studly($type);
        $import_class = config($this->__config_name . '.imports.' . $type, null);
        return new $import_class($this);
    }

    public function generalGetModelEntity(): mixed
    {
        return $this->{$this->snakeEntity() . '_model'};
    }

    public function generalPrepareFind(?callable $callback = null, ?array $attributes = null): Model
    {
        $attributes ??= request()->all();
        $camelEntity = $this->camelEntity();
        $model = $this->{$camelEntity}()
            ->conditionals(isset($callback), function ($query) use ($callback, $attributes) {
                $this->mergeCondition($callback($query));
            })
            ->when(isset($attributes['id']), function ($query) use ($attributes) {
                $query->where($this->usingEntity()->getKeyName(), $attributes['id']);
            })
            ->when(isset($attributes['uuid']), function ($query) use ($attributes) {
                $query->where('uuid', $attributes['uuid']);
            })
            ->with($this->showUsingRelation())
            ->first();
        return $this->entityData($model);
    }

    public function generalFind(?callable $callback = null): ?array
    {
        $methods = $this->getEntityMethods();
        $model = $this->{$methods['prepareFind']}($callback);
        if (!isset($model)) return null;
        return $this->showEntityResource(function () use ($model) {
            return $model;
        });
    }

    public function forgetTagsEntity(?string $entity = null): void
    {
        $this->forgetTags($entity ?? $this->snakeEntity());
    }

    public function generalPrepareShow(?Model $model = null, ?array $attributes = null): Model
    {
        $attributes ??= request()->all();
        if (!isset($model)) {
            $valid = $attributes['id'] ?? $attributes['uuid'] ?? null;
            if (!isset($valid)) throw new \Exception('No id or uuid provided', 422);
            $model = $this->{$this->camelEntity()}()
                ->with($this->showUsingRelation())
                ->when(isset($attributes['id']), fn($query) => $query->where('id', $attributes['id']))
                ->when(isset($attributes['uuid']), fn($query) => $query->where('uuid', $attributes['uuid']))
                ->firstOrFail();
        } else {
            $model->load($this->showUsingRelation());
        }
        return $this->entityData($model);
    }

    public function generalShow(null|Collection|Model $model = null): array
    {
        $methods = $this->getEntityMethods();
        return $this->showEntityResource(function () use ($model, $methods) {
            return $this->{$methods['prepareShow']}($model);
        });
    }

    private function preparePaginateBuilder(PaginateData $paginate_dto): LengthAwarePaginator
    {
        return $this->{$this->camelEntity()}()
            ->with($this->viewUsingRelation())
            ->paginate(...$paginate_dto->toArray())
            ->appends(request()->all());
    }

    public function generalPrepareViewPaginate(PaginateData $paginate_dto): LengthAwarePaginator
    {
        $snake_entity = $this->snakeEntity();
        if (isset($this->__cache['index'])) {
            $this->addSuffixCache($this->__cache['index'], $snake_entity . "-index", 'paginate');
            return $this->{$snake_entity . '_model'} = $this->cacheWhen(!$this->isSearch(), $this->__cache['index'], function () use ($paginate_dto) {
                return $this->preparePaginateBuilder($paginate_dto);
            });
        } else {
            return $this->preparePaginateBuilder($paginate_dto);
        }
    }

    public function generalViewPaginate(?PaginateData $paginate_dto = null): array
    {
        $methods = $this->getEntityMethods();
        $request = request();
        return $this->viewEntityResource(function () use ($paginate_dto, $methods) {
            return $this->{$methods['prepareViewPaginate']}($paginate_dto ?? $this->requestDTO(PaginateData::class));
        }, ['rows_per_page' => [$request->per_page ?? $request->perPage ?? $request->limit ?? 10]]);
    }

    public function generalPrepareViewList(?array $attributes = null): Collection
    {
        $models = $this->{$this->camelEntity()}()->with($this->viewUsingRelation())->get();
        return $this->entityData($models);
    }

    public function generalViewList(): array
    {
        $methods = $this->getEntityMethods();
        return $this->viewEntityResource(function () use ($methods) {
            return $this->{$methods['prepareViewList']}();
        });
    }

    public function generalUniversalPrepareStore(mixed $dto = null): Model
    {
        $methods = $this->getEntityMethods();
        return $this->{$methods['prepareStore']}($dto ?? $this->requestDTO($this->getEntityDataContract()));
    }

    public function generalPrepareStore(mixed $dto = null): Model
    {
        if (is_array($dto)) $dto = $this->requestDTO($this->getEntityDataContract());
        $model = $this->usingEntity()->updateOrCreate([
            'id' => $dto->id ?? null
        ], [
            'name' => $dto->name
        ]);
        $this->fillingProps($model, $dto->props);
        $model->save();
        return $this->entityData($model);
    }

    public function generalPrepareStoreMultiple(array $datas): Collection
    {
        $collection = new Collection();
        $contract = $this->getEntityDataContract();
        foreach ($datas as $data) {
            $collection->push($this->generalPrepareStore($this->requestDTO($contract, $data)));
        }
        return $collection;
    }

    public function generalStore(mixed $dto = null): array
    {
        $methods = $this->getEntityMethods();
        $transaction = $this->transaction(function () use (&$dto, $methods) {
            $dto ??= $this->requestDTO($this->getEntityDataContract());
            $this->current_dto = $dto;
            $this->current_model = $model = $this->{$methods['prepareStore']}($dto);

            return isset($model)
                ? $this->{$methods['show']}($model)
                : [];
        });
        $this->afterTransaction($this->current_model, $this->current_dto, $transaction);
        return $transaction;
    }

    public function afterTransaction(Model $current_model, mixed $dto, ?array $response = null): self
    {
        return $this;
    }

    public function generalStoreMultiple(array $datas): array
    {
        $methods = $this->getEntityMethods();
        return $this->transaction(function () use ($datas, $methods) {
            $results = $this->{$methods['prepareStoreMultiple']}($datas);
            $showMethod = $methods['show'];
            $results->transform(function ($model) use ($showMethod) {
                return $this->{$showMethod}($model);
            });
            return $results->toArray();
        });
    }

    public function generalPrepareUpdate(mixed $dto): Model
    {
        if (is_array($dto)) $dto = $this->requestDTO($this->getEntityUpdateDataContract());
        $model = $this->usingEntity()->updateOrCreate([
            'id' => $dto->id
        ], [
            'name' => $dto->name
        ]);
        $this->fillingProps($model, $dto->props);
        $model->save();
        return $this->entityData($model);
    }

    public function generalUpdate(mixed $dto = null)
    {
        $methods = $this->getEntityMethods();
        return $this->transaction(function () use ($dto, $methods) {
            return $this->{$methods['show']}(
                $this->{$methods['prepareUpdate']}($dto ?? $this->requestDTO($this->getEntityUpdateDataContract()))
            );
        });
    }

    public function generalPrepareDelete(?array $attributes = null): bool
    {
        $entity = $this->snakeEntity();
        $attributes ??= request()->all();
        if (!$attributes['id']) throw new \Exception('No id provided', 422);
        $result = $this->usingEntity()->findOrFail($attributes['id'])->delete();
        $this->forgetTagsEntity($entity);
        return $result;
    }

    public function generalDelete(): bool
    {
        $methods = $this->getEntityMethods();
        return $this->transaction(function () use ($methods) {
            return $this->{$methods['prepareDelete']}();
        });
    }

    public function generalSchemaModel(mixed $conditionals = null): Builder
    {
        $this->booting();
        if (!config('app.set-param-logic', false)) $this->setParamLogic();

        $model = $this->usingEntity();
        $fillable = $this->getEntityFillable(); // OPTIMIZATION: Cached

        // Route to Elasticsearch if enabled on model
        $builder = method_exists($model, 'isElasticSearchEnabled') && $model->isElasticSearchEnabled() && config('elasticsearch.enabled', false)
            ? $model->withElasticSearch($this->getParamLogic())
            : $model->withParameters($this->getParamLogic());

        $orderBy = $this->__order_by_created_at;

        return $builder
            ->conditionals($this->mergeCondition($conditionals ?? []))
            ->when(!$orderBy, function ($query) use ($fillable) {
                if (in_array('name', $fillable)) {
                    $query->orderBy('name', 'asc');
                }
            })
            ->when(is_string($orderBy), function ($query) use ($fillable, $orderBy) {
                if (in_array('created_at', $fillable)) {
                    $query->orderBy('created_at', $orderBy);
                }
            })
            ->when(is_array($orderBy), function ($query) use ($orderBy) {
                $query->orderBy(...$orderBy);
            });
    }

    public function setParamLogic(?string $logic = null, ?array $optionals = null): self
    {
        static::$param_logic = $logic;

        $searchValue = request()->search_value;
        if (isset($searchValue)) {
            static::$param_logic ??= 'or';

            // OPTIMIZATION: Use cached casts and array functions
            $excluded = ['props', 'created_at', 'updated_at', 'deleted_at'];
            $modelCasts = $this->getEntityCasts();
            $validCasts = array_diff($modelCasts, $excluded);

            // Build searches array efficiently
            $searches = [];
            foreach ($validCasts as $cast) {
                $searches['search_' . $cast] = $searchValue;
            }

            $optionals ??= [];
            $params = array_merge($searches, $optionals);
            request()->replace($params);
        } else {
            static::$param_logic ??= 'and';
        }

        config(['app.set-param-logic' => true]);
        return $this;
    }

    public function getParamLogic(): string
    {
        return static::$param_logic;
    }

    /**
     * Clear cached values (useful for testing or when entity changes)
     */
    public function clearEntityCache(): self
    {
        $this->__camel_entity = null;
        $this->__snake_entity = null;
        $this->__entity_methods = null;
        $this->__entity_data_contract = null;
        $this->__cached_entity_model = null;
        return $this;
    }

    /**
     * Clear static caches (for Octane compatibility)
     */
    public static function flushStaticCaches(): void
    {
        static::$__fillable_cache = [];
        static::$__casts_cache = [];
        static::$param_logic = null;
    }
}
