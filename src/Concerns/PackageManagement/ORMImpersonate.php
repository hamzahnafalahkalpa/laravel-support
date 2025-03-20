<?php

namespace Hanafalah\LaravelSupport\Concerns\PackageManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

trait ORMImpersonate
{

    private $__conditionals;

    private function attributeChecking(callable $callback, ?array $attributes = null)
    {
        $attributes ??= $this->__attributes;
        if (count($attributes) > 0) {
            if (count($this->__attributes) == 0) $this->__attributes = $attributes;
            $value = $callback($attributes);
            $this->beforeResolve($attributes, $this->__add, $this->__guard);
            return $value;
        } else {
            throw new \Exception('Attributes cannot be null');
        }
    }

    public function updateOrCreate(?array $attributes = null): Model
    {
        $attributes = $this->mergeProps($attributes);
        return $this->attributeChecking(function ($attributes) {
            $fills = $this->createInit($attributes);
            if (count($fills) > 1) {
                list($guard, $add) = $fills;
            } else {
                $guard = [];
                $add   = $fills[0];
            }
            $model = $this->getModel(true)->where(function ($q) use ($guard) {
                foreach ($this->__guard as $key) {
                    if (!isset($guard[$key])) {
                        $q->whereNull($key);
                    } else {
                        $q->where($key, $guard[$key]);
                    }
                }
            })->first();

            if (!isset($model)) $model = $this->getModel(true);
            $add = $this->mergeArray($guard, $add);
            foreach ($add as $key => $value) {
                $model->{$key} = $value;
            }

            $model->save();
            $model->refresh();
            return self::$__model = $model;
        }, $attributes);
    }

    public function removeFromAttribute(string|array $key): array
    {
        $keys = $this->mustArray($key);
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->__attributes)) unset($this->__attributes[$key]);
        }
        return $this->__attributes;
    }

    public function create(?array $attributes = null): Model
    {
        return $this->attributeChecking(function ($attributes) {
            $adds = $attributes;
            $adds = $this->mergeArray($attributes[static::$__prop_column] ?? [], $adds);
            unset($adds[static::$__prop_column]);

            $model = $this->getModel(true);
            foreach ($adds as $key => $value) $model->{$key} = $value;
            $model->save();
            $model->refresh();
            return self::$__model = $model;
        }, $attributes);
    }

    public function mergeCondition(mixed $conditionals): array
    {
        $conditionals ??= [];
        $conditionals = $this->mustArray($conditionals);
        return $this->mergeArray($this->mustArray($this->__conditionals ?? []), $conditionals);
    }

    public function get(mixed $conditionals = null): Collection
    {
        return $this->getModel(true)->conditionals($this->mergeCondition($conditionals))->get();
    }

    public function paginate(mixed $conditionals = null, int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null, ?int $total = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = request()->perPage ?? $perPage;
        return $this->getModel(true)->conditionals($conditionals)->paginate($perPage, $columns,  $pageName, $page ?? request()->page ?? 1, $total);
    }

    public function first(mixed $conditionals = null): Model|null
    {
        return $this->getModel(true)->conditionals($this->mergeCondition($conditionals))->first();
    }

    /**
     * Find a model by given id or if not provided by request id
     *
     * @param mixed $id
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function refindByUUID(string $uuid = null, string $uuid_name = 'uuid'): Model|null
    {
        $uuid ??= request()->uuid;
        return self::$__model = $this->getModel()->uuid($uuid, $uuid_name)->first();
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
    public function conditionals(mixed $conditionals): self
    {
        $this->__conditionals = $this->mergeCondition($conditionals ?? []);
        return $this;
    }

    /**
     * Find a model by given id or if not provided by request id
     *
     * @param mixed $id
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function refind(mixed $id = null): Model|null
    {
        $id ??= request()->id;
        return self::$__model = (static::$__model ?? $this->getModel())->conditionals($this->__conditionals ?? [])->find($id);
    }

    public function remove(mixed $conditionals): bool
    {
        return $this->getModel()->conditionals($this->mergeCondition($conditionals))->delete();
    }

    /**
     * Remove a model by given id or if not provided by request id
     *
     * @param mixed $id
     * @return boolean
     */
    public function removeById(mixed $id = null): bool
    {
        $id ??= request()->id;
        if (isset($id)) {
            return $this->getModel()->conditionals($this->__conditionals ?? [])->find($id)->delete();
        }
        return false;
    }

    /**
     * Remove a model by given id or if not provided by request id
     *
     * @param mixed $id
     * @return boolean
     */
    public function removeByUuid(mixed $uuid = null, string $uuid_name = 'uuid'): bool
    {
        $uuid ??= request()->uuid;
        if (isset($uuid)) return $this->getModel()->conditionals($this->__conditionals ?? [])->uuid($uuid, $uuid_name)->delete();
        return false;
    }
}
