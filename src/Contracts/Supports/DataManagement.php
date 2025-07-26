<?php

namespace Hanafalah\LaravelSupport\Contracts\Supports;

use Hanafalah\LaravelSupport\Contracts\Data\PaginateData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface DataManagement
{
    //CLASS MANIPULATION
    public function useSchema(string $className): DataManagement;
    public function getClass(): mixed;
    public function flushTagsFrom(string $category, ?string $tags = null, ?string $suffix = null);
    public function schemaContract(string $contract);
    public function moveTo(string $field, array $new_fields): self;
    public function conditionals(mixed $conditionals): self;
    public function mergeCondition(mixed $conditionals): array;
    public function usingEntity(): Model;
    public function entityData(mixed $model = null): mixed;
    public function viewEntityResource(callable $callback,array $options = []): array;
    public function showEntityResource(callable $callback,array $options = []): array;
    public function autolist(?string $response = 'list',?callable $callback = null): mixed;
    public function generalExport(string $type): mixed;
    public function generalGetModelEntity(): mixed;
    public function generalPrepareFind(?callable $callback = null, ? array $attributes = null): Model;
    public function generalFind(? callable $callback = null): ?array;
    public function camelEntity(): string;
    public function snakeEntity(): string;
    public function forgetTagsEntity(?string $entity = null): void;
    public function generalPrepareShow(? Model $model = null, ? array $attributes = null): Model;
    public function generalShow(? Model $model = null): array;
    public function generalPrepareViewPaginate(PaginateData $paginate_dto): LengthAwarePaginator;
    public function generalViewPaginate(?PaginateData $paginate_dto = null): array;
    public function generalPrepareViewList(? array $attributes = null): Collection;
    public function generalViewList(): array;
    public function generalPrepareStore(mixed $dto = null): Model;
    public function generalPrepareStoreMultiple(array $datas): Collection;
    public function generalStore(mixed $dto = null): array;
    public function generalStoreMultiple(array $datas);
    public function generalPrepareUpdate(mixed $dto): Model;
    public function generalUpdate(mixed $dto = null);
    public function generalPrepareDelete(? array $attributes = null): bool;
    public function generalDelete(): bool;
    public function generalSchemaModel(mixed $conditionals = null): Builder;
    public function setParamLogic(string $logic = 'and', bool $search_value = true, ?array $optionals = []): self;
    public function getParamLogic(): string;


}
