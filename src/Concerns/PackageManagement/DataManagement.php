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

trait DataManagement
{
    private $__conditionals;
    protected mixed $__order_by_created_at = 'desc'; //asc, desc, false
    public static $param_logic = 'and';

    use RequestManipulation;
    use Support\HasRepository;
    use DatabaseConfiguration\HasModelConfiguration;
    use ServiceProvider\HasConfiguration;
    use Package\HasCallMethod;

    protected function methodHandler(string $method,array $methods){
        $arguments = $this->getCallArguments() ?? [];
        if (isset($methods[$method])){
            return $this->{$methods[$method]}(...$arguments);
        }
        return null;
    }

    public function __callSchemaEloquent(){
        $method = $this->getCallMethod();

        $entity = $this->__entity;

        $result = $this->methodHandler($method,[
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
            "prepareStore{$entity}"         => 'generalPrepareStore',
            "prepareStoreMultiple{$entity}" => 'generalPrepareStoreMultiple',
            "update{$entity}"               => 'generalUpdate',
            "prepareUpdate{$entity}"        => 'generalPrepareUpdate',
            Str::camel($entity)             => 'generalSchemaModel',
        ]);
        if (isset($result)) return $result;
    }

    /**
     * Add conditionals to the query
     *
     * If the given argument is callable, it will be called with the query builder as argument.
     * If the given argument is an array, it will be passed to the `where` method on the query builder.
     * If the given argument is a string, it will be passed to the `whereRaw` method on the query builder.
     *
     * @param mixed $conditionals
     * @return self
     */
    public function conditionals(mixed $conditionals): self{
        $this->__conditionals = $this->mergeCondition($conditionals ?? []);
        return $this;
    }

    public function mergeCondition(mixed $conditionals): array{
        $conditionals ??= [];
        $conditionals = $this->mustArray($conditionals);
        return $this->mergeArray($this->mustArray($this->__conditionals ?? []), $conditionals);
    }

    protected function viewUsingRelation(): array{
        $model = $this->usingEntity();
        return method_exists($model,'viewUsingRelation') ? $this->usingEntity()->viewUsingRelation() : [];
    }

    protected function showUsingRelation(): array{
        $model = $this->usingEntity();
        return method_exists($model,'showUsingRelation') ? $this->usingEntity()->showUsingRelation() : [];
    }

    public function usingEntity(): Model{
        return $this->{$this->__entity.'Model'}();
    }

    public function entityData(mixed $model = null): mixed{
        return $this->{$this->snakeEntity().'_model'} = $model;
    }

    public function viewEntityResource(callable $callback,array $options = []): array{
        return $this->transforming($this->usingEntity()->getViewResource(),function() use ($callback){
            return $callback();
        },$options);
    }

    public function showEntityResource(callable $callback,array $options = []): array{
        return $this->transforming($this->usingEntity()->getShowResource(),function() use ($callback){
            return $callback();
        },$options);
    }


    public function autolist(?string $response = 'list',?callable $callback = null): mixed{
        if (isset($callback)) $this->conditionals($callback);
        if (isset(request()->search_except_id)){
            $this->conditionals(function($query){
                $query->where('id','!=',request()->search_except_id);
            });
        }
        $reference_type = request()->search_reference_type ?? null;
        switch ($response) {
            case 'list'     : return $this->{'view'.$this->__entity.'List'}($reference_type);break;
            case 'paginate' : return $this->{'view'.$this->__entity.'Paginate'}($reference_type);break;
            case 'find'     : return $this->{'find'.$this->__entity}($reference_type);break;
        }
        abort(404);
    }

    public function generalExport(string $type): mixed{
        if (!isset($this->__config_name)) throw new \Exception('No config name provided', 422);
        $type = Str::studly($type);
        $export_class = config($this->__config_name.'.exports.'.$type,null);
        return new $export_class($this);
    }

    public function generalGetModelEntity(): mixed{
        $entity = $this->snakeEntity();
        return $this->{$entity.'_model'};
    }

    public function generalPrepareFind(?callable $callback = null, ? array $attributes = null): Model{
        $attributes ??= request()->all();
        $model = $this->generalGetModelEntity()->conditionals(isset($callback),function($query) use ($callback){
            $this->mergeCondition($callback($query));
        })->with($this->showUsingRelation())->first();
        return $this->entityData($model);
    }   

    public function generalFind(? callable $callback = null): ?array{
        $model = $this->{'prepareFind'.$this->__entity}($callback);
        if (!isset($model)) return null;
        return $this->showEntityResource(function() use ($model){
            return $model;
        });
    }

    public function camelEntity(): string{
        return Str::camel($this->__entity);
    }

    public function snakeEntity(): string{
        return Str::snake($this->__entity);
    }

    public function forgetTagsEntity(?string $entity = null): void{
        $this->forgetTags($entity ?? $this->snakeEntity());
    }

    public function generalPrepareShow(? Model $model = null, ? array $attributes = null): Model{
        $attributes ??= request()->all();
        $model ??= (\method_exists($this, 'get'.$this->__entity)) ? $this->{'get'.$this->__entity}() : $this->generalGetModelEntity();
        if (!isset($model)){
            $valid = $attributes['id'] ?? $attributes['uuid'] ?? null;
            if (!isset($valid)) throw new \Exception('No id or uuid provided', 422);
            $model = $this->{$this->camelEntity()}()
                        //   ->with($this->showUsingRelation())
                          ->when(isset($attributes['id']),fn($query)   => $query->where('id', $attributes['id']))
                          ->when(isset($attributes['uuid']),fn($query) => $query->where('uuid', $attributes['uuid']))
                          ->firstOrFail();
        }else{
            $model->load($this->showUsingRelation());
        }
        return $this->entityData($model);
    }   

    public function generalShow(? Model $model = null): array{
        return $this->showEntityResource(function() use ($model){
            return $this->{'prepareShow'.$this->__entity}($model);
        });
    }

    private function preparePaginateBuilder(PaginateData $paginate_dto): LengthAwarePaginator{
            return $this->{$this->camelEntity()}()->with($this->viewUsingRelation())->paginate(...$paginate_dto->toArray())->appends(request()->all());
    }

    public function generalPrepareViewPaginate(PaginateData $paginate_dto): LengthAwarePaginator{
        $snake_entity = $this->snakeEntity();
        if (isset($this->__cache['index'])){
            $this->addSuffixCache($this->__cache['index'], $snake_entity."-index", 'paginate');
            return $this->{$snake_entity.'_model'} = $this->cacheWhen(!$this->isSearch(), $this->__cache['index'], function () use ($paginate_dto) {
                return $this->preparePaginateBuilder($paginate_dto);
            });
        }else{
            return $this->preparePaginateBuilder($paginate_dto);
        }
    }

    public function generalViewPaginate(?PaginateData $paginate_dto = null): array{
        return $this->viewEntityResource(function() use ($paginate_dto){
            return $this->{"prepareView".$this->__entity."Paginate"}($paginate_dto ?? $this->requestDTO(PaginateData::class));
        }, ['rows_per_page' => [50]]);
    }

    public function generalPrepareViewList(? array $attributes = null): Collection{
        $models = $this->{$this->camelEntity()}()->with($this->viewUsingRelation())->get();
        return $this->entityData($models);
    }

    public function generalViewList(): array{
        return $this->viewEntityResource(function(){
            return $this->{'prepareView'.$this->__entity.'List'}();
        });
    }

    public function generalPrepareStore(mixed $dto = null): Model{
        if (is_array($dto)) $dto = $this->requestDTO(config("app.contracts.{$this->__entity}Data",null));
        $model = $this->usingEntity()->updateOrCreate([
            'id' => $dto->id ?? null
        ], [
            'name' => $dto->name
        ]);
        $this->fillingProps($model,$dto->props);
        $model->save();
        return $this->entityData($model);
    }

    public function generalPrepareStoreMultiple(array $datas): Collection{
        $collection = new Collection();
        foreach ($datas as $data) {
            $collection->push($this->generalPrepareStore($this->requestDTO(config("app.contracts.{$this->__entity}Data",$data))));
        }
        return $collection;
    }

    public function generalStore(mixed $dto = null): array{
        return $this->transaction(function () use ($dto) {
            return $this->{'show'.$this->__entity}(
                $this->{'prepareStore'.$this->__entity}($dto ?? $this->requestDTO(config("app.contracts.{$this->__entity}Data",null))) //RETURN MODEL
            );
        });
    }

    public function generalStoreMultiple(array $datas){
        return $this->transaction(function () use ($datas) {
            return $this->{'show'.$this->__entity}($this->{'prepareStoreMultiple'.$this->__entity}($datas));
        });
    }

    public function generalPrepareUpdate(mixed $dto): Model{
        if (is_array($dto)) $dto = $this->requestDTO(config("app.contracts.{$this->__entity}UpdateData",null));
        $model = $this->usingEntity()->updateOrCreate([
            'id' => $dto->id
        ], [
            'name' => $dto->name
        ]);
        $this->fillingProps($model,$dto->props);
        $model->save();
        return $this->entityData($model);
    }

    public function generalUpdate(mixed $dto = null){
        return $this->transaction(function () use ($dto) {
            return $this->{'show'.$this->__entity}($this->{'prepareUpdate'.$this->__entity}($dto ?? $this->requestDTO(config("app.contracts.{$this->__entity}UpdateData",null))));
        });
    }

    public function generalPrepareDelete(? array $attributes = null): bool{
        $entity = $this->snakeEntity();
        $attributes ??= \request()->all();
        if (!$attributes['id']) throw new \Exception('No id provided', 422);
        $result = $this->usingEntity()->findOrFail($attributes['id'])->delete();
        $this->forgetTagsEntity($entity);
        return $result;
    }

    public function generalDelete(): bool{
        return $this->transaction(function () {
            return $this->{'prepareDelete'.$this->__entity}();
        });
    }

    public function generalSchemaModel(mixed $conditionals = null): Builder{
        $this->booting();
        $this->setParamLogic();
        $model = $this->usingEntity();
        $fillable = $model->getFillable();
        return $model->withParameters($this->getParamLogic())
                    ->conditionals($this->mergeCondition($conditionals ?? []))
                    ->when(!$this->__order_by_created_at, function ($query) use ($fillable) {
                        $query->when(in_array('name', $fillable), function ($query) {
                            $query->orderBy('name', 'asc');
                        });
                    })->when(is_string($this->__order_by_created_at), function ($query) use ($fillable) {
                        $query->when(in_array('created_at', $fillable), function ($query) {
                            $query->orderBy('created_at', $this->__order_by_created_at);
                        });
                    })->when(is_array($this->__order_by_created_at), function ($query) {
                        $query->orderBy(...$this->__order_by_created_at);
                    });
    }

    public function setParamLogic(string $logic = 'and', bool $search_value = true, ?array $optionals = []): self
    {
        static::$param_logic = $logic;
        if ($search_value && isset(request()->search_value)){
            $model_casts = array_keys($this->usingEntity()->getCasts());
            $searches = [];
            foreach ($model_casts as $cast) {
                $searches['search_'.$cast] = request()->search_value;
            }
            $searches['search_value'] = null;
            request()->merge($searches,...$optionals);
        }
        return $this;
    }

    public function getParamLogic(): string{
        return static::$param_logic;
    }
}
