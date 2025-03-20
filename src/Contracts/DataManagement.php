<?php

namespace Zahzah\LaravelSupport\Contracts;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface DataManagement
{
    //CLASS MANIPULATION
    public function useSchema(string $className): DataManagement;
    public function getClass(): mixed;
    public function fork(callable $callback): DataManagement;
    public function child(callable $callback): DataManagement;
    public function booting(): self;
    public function morphs(string $key = null): null|string|array;
    public function toProps(array $fields = []): self;
    public function flushTagsFrom(string $category,? string $tags = null,? string $suffix = null);
    public function setParamLogic(string $logic): self;
    public function getParamLogic(): string;
    public function schemaContract(string $contract);

    //REQUEST MANIPULATION
    public function moveTo(string $field,array $new_fields): self;

    //ORM MANIPULATION
    public function add(? array $attributes = null): DataManagement;
    public function adds(? array $attributes = null,array $parent_id=[]): DataManagement;
    public function change(? array $attributes = null): DataManagement;
    public function setAdd(string|array $attributes,bool $overwirte = false): DataManagement;
    public function setGuard(string|array $attributes,bool $overwirte = false): DataManagement;
    public function conditionals(mixed $conditionals): self;
    public function inheritenceLoad(object &$model,string $relation,?callable $callback = null): void;

    //MODEL MANIPULATION
    public function getModel(bool $new = false,string $model_name = null): mixed;
    public function get(mixed $collections = null): Collection;
    public function paginate(mixed $conditionals = null, int $perPage = 15, array $columns = ['*'], string $pageName = 'page',? int $page = null,? int $total = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function first(mixed $collections = null): Model|null;
    public function refindByUUID(string $uuid = null, string $uuid_name = 'uuid'): Model|null;
    public function refind(mixed $id = null): Model|null;
    public function updateOrCreate(? array $attributes = null): Model;
    public function create(? array $attributes = null): Model;
    public function remove(mixed $conditionals): bool;
    public function removeById(mixed $id = null): bool;
    public function removeByUuid(mixed $uuid = null, string $uuid_name = 'uuid'): bool;
}