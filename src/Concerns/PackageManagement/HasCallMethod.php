<?php

namespace Hanafalah\LaravelSupport\Concerns\PackageManagement;

use Hanafalah\LaravelSupport\Concerns\Support\HasCall;
use Hanafalah\LaravelSupport\Contracts\Data\PaginateData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

trait HasCallMethod
{
    use HasCall;

    /**
     * Calls the custom method for the current instance.
     *
     * It will first check if the method is a custom method, and if so, it will
     * call the method with the given arguments.
     *
     * @return mixed|null
     */
    public function __callMethod()
    {
        $method = $this->getCallMethod();
        $arguments = $this->getCallArguments() ?? [];

        if (Str::startsWith($method, 'call') && Str::endsWith($method, 'Method')) {
            $key = Str::between($method, 'call', 'Method');
            if (!method_exists($this, $key)) {
                return $this->{$key}($this->getCallArguments());
            }
        }

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
        
    }

    protected function viewUsingRelation(): array{
        return $this->{$this->__entity.'Model'}()->viewUsingRelation() ?? [];
    }

    protected function showUsingRelation(): array{
        return $this->{$this->__entity.'Model'}()->showUsingRelation() ?? [];
    }

    public function autolist(?string $response = 'list',?callable $callback = null): mixed{
        if (isset($callback)) $this->condition($callback);
        $reference_type = request()->search_reference_type ?? null;
        switch ($response) {
            case 'list':
                return $this->{'view'.$this->__entity.'List'}($reference_type);
            break;
            case 'paginate':
                return $this->{'view'.$this->__entity.'Paginate'}($reference_type);
            break;
            case 'find':
                return $this->{'find'.$this->__entity}($reference_type);
            break;
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
}
