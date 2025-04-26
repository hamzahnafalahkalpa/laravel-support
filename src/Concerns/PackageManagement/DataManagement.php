<?php

namespace Hanafalah\LaravelSupport\Concerns\PackageManagement;

use Hanafalah\LaravelSupport\{
    Concerns\Support,
    Concerns\DatabaseConfiguration,
    Concerns\ServiceProvider,
    Concerns\PackageManagement as Package
};
use Hanafalah\LaravelSupport\Concerns\Support\RequestManipulation;
use Hanafalah\LaravelSupport\Data\PaginateData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

trait DataManagement
{
    private $__conditionals;
    protected mixed $__order_by_created_at = 'desc'; //asc, desc, false

    use RequestManipulation;
    use Support\HasRepository;
    use DatabaseConfiguration\HasModelConfiguration;
    use ServiceProvider\HasConfiguration;
    use Package\HasCallMethod;

    public function __callSchemaEloquent(){
        $method = $this->getCallMethod();
        $arguments = $this->getCallArguments() ?? [];

        if ($method !== 'show' && Str::startsWith($method, 'show'.$this->__entity)){
            return $this->generalShow(...$arguments);
        }

        if ($method !== 'prepareShow' && Str::startsWith($method, 'prepareShow'.$this->__entity)){
            return $this->generalPrepareShow(...$arguments);
        }

        if ($method !== 'prepareView' && Str::startsWith($method, 'prepareView'.$this->__entity) && Str::endsWith($method,'Paginate')){
            return $this->generalPrepareViewPaginate(...$arguments);
        }

        if ($method !== 'view' && Str::startsWith($method, 'view'.$this->__entity) && Str::endsWith($method,'Paginate')){
            return $this->generalViewPaginate();
        }

        if ($method !== 'prepareView' && Str::startsWith($method, 'prepareView'.$this->__entity) && Str::endsWith($method,'List')){
            return $this->generalPrepareViewList(...$arguments);
        }

        if ($method !== 'view' && Str::startsWith($method, 'view'.$this->__entity) && Str::endsWith($method,'List')){
            return $this->generalViewList();
        }

        if ($method !== 'generalFind' && Str::startsWith($method, 'generalFind'.$this->__entity)){
            return $this->generalPrepareFind(...$arguments);
        }

        if ($method == 'find'.$this->__entity){
            return $this->generalFind(...$arguments);
        }

        if ($method !== 'prepareDelete' && Str::startsWith($method, 'prepareDelete'.$this->__entity)){
            return $this->generalPrepareDelete(...$arguments);
        }

        if ($method !== 'delete' && Str::startsWith($method, 'delete'.$this->__entity)){
            return $this->generalDelete();
        }
        
        if ($method !== 'store' && Str::startsWith($method, 'store'.$this->__entity)){
            return $this->generalStore();
        }

        if (Str::startsWith($method, Str::camel($this->__entity))){
            return $this->generalSchemaModel();
        }
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
        return $this->{$this->__entity.'Model'}()->viewUsingRelation() ?? [];
    }

    protected function showUsingRelation(): array{
        return $this->{$this->__entity.'Model'}()->showUsingRelation() ?? [];
    }

    public function autolist(?string $response = 'list',?callable $callback = null): mixed{
        if (isset($callback)) $this->conditionals($callback);
        $reference_type = request()->search_reference_type ?? null;
        switch ($response) {
            case 'list'     : return $this->{'view'.$this->__entity.'List'}($reference_type);break;
            case 'paginate' : return $this->{'view'.$this->__entity.'Paginate'}($reference_type);break;
            case 'find'     : return $this->{'find'.$this->__entity}($reference_type);break;
        }
        abort(404);
    }

    public function generalGetModelEntity(): mixed{
        $entity = Str::snake($this->__entity);
        return static::${$entity.'_model'};
    }

    public function generalPrepareFind(?callable $callback = null, ? array $attributes = null): Model{
        $attributes ??= request()->all();
        $model = $this->generalGetModelEntity()->conditionals(isset($callback),function($query) use ($callback){
            $this->mergeCondition($callback($query));
        })->with($this->showUsingRelation())->first();
        return static::${Str::snake($this->__entity).'_model'} = $model;
    }   

    public function generalFind(? callable $callback = null): array{
        return $this->showEntityResource(function() use ($callback){
            return $this->{'prepareFind'.$this->__entity}($callback);
        });
    }

    public function generalPrepareShow(? Model $model = null, ? array $attributes = null): Model{
        $attributes ??= request()->all();
        $model ??= (\method_exists($this, 'get'.$this->__entity)) ? $this->{'get'.$this->__entity}() : $this->generalGetModelEntity();
        if (!isset($model)){
            $id = $attributes['id'] ?? null;
            if (!isset($id)) throw new \Exception('No id provided', 422);
            $entity = Str::camel($this->__entity);
            $model = $this->{$entity}()->with($this->showUsingRelation())->findOrFail($id);
        }else{
            $model->load($this->showUsingRelation());
        }
        return static::${Str::snake($this->__entity).'_model'} = $model;
    }   

    public function generalShow(? Model $model = null): array{
        return $this->showEntityResource(function() use ($model){
            return $this->{'prepareShow'.$this->__entity}($model);
        });
    }

    public function generalPrepareViewPaginate(PaginateData $paginate_dto): LengthAwarePaginator{
        $snake_entity = Str::snake($this->__entity);
        $this->addSuffixCache($this->__cache['index'], $snake_entity."-index", 'paginate');
        return static::${$snake_entity.'_model'} = $this->cacheWhen(!$this->isSearch(), $this->__cache['index'], function () use ($paginate_dto) {
            return $this->{Str::camel($this->__entity)}()->with($this->viewUsingRelation())->paginate(...$paginate_dto->toArray())->appends(request()->all());
        });
    }

    public function generalViewPaginate(?PaginateData $paginate_dto = null): array{
        return $this->viewEntityResource(function() use ($paginate_dto){
            return $this->{"prepareView".$this->__entity."Paginate"}($paginate_dto ?? $this->requestDTO(PaginateData::class));
        }, ['rows_per_page' => [50]]);
    }

    public function generalPrepareViewList(? array $attributes = null): Collection{
        return static::${Str::snake($this->__entity).'_model'} = $this->{Str::camel($this->__entity)}()->with($this->viewUsingRelation())->get();
    }

    public function generalViewList(): array{
        return $this->viewEntityResource(function(){
            return $this->{'prepareView'.$this->__entity.'List'}();
        });
    }

    public function generalStore(mixed $dto = null){
        return $this->transaction(function () use ($dto) {
            return $this->{'show'.$this->__entity}($this->{'prepareStore'.$this->__entity}($dto ?? $this->requestDTO(config("app.contracts.{$this->__entity}Data",null))));
        });
    }

    public function generalPrepareDelete(? array $attributes = null): bool{
        $entity = Str::snake($this->__entity);
        $attributes ??= \request()->all();
        if (!$attributes['id']) throw new \Exception('No id provided', 422);
        $result = $this->{$this->__entity.'Model'}()->findOrFail($attributes['id'])->delete();
        $this->forgetTags($entity);
        return $result;
    }

    public function generalDelete(): bool{
        return $this->transaction(function () {
            return $this->{'prepareDelete'.$this->__entity}();
        });
    }

    public function generalSchemaModel(mixed $conditionals = null): Builder{
        $this->booting();
        $model = $this->{$this->__entity.'Model'}();
        
        $fillable = $model->getFillable();
        return $model->withParameters()
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
}
