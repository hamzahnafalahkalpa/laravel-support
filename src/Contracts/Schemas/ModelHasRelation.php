<?php

namespace Hanafalah\LaravelSupport\Contracts\Schemas;

use Hanafalah\LaravelSupport\Contracts\Data\ModelHasRelationData;
//use Hanafalah\LaravelSupport\Contracts\Data\ModelHasRelationUpdateData;
use Hanafalah\LaravelSupport\Contracts\Supports\DataManagement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @see \Hanafalah\LaravelSupport\Schemas\ModelHasRelation
 * @method mixed export(string $type)
 * @method self conditionals(mixed $conditionals)
 * @method array updateModelHasRelation(?ModelHasRelationData $model_has_relation_dto = null)
 * @method Model prepareUpdateModelHasRelation(ModelHasRelationData $model_has_relation_dto)
 * @method bool deleteModelHasRelation()
 * @method bool prepareDeleteModelHasRelation(? array $attributes = null)
 * @method mixed getModelHasRelation()
 * @method ?Model prepareShowModelHasRelation(?Model $model = null, ?array $attributes = null)
 * @method array showModelHasRelation(?Model $model = null)
 * @method Collection prepareViewModelHasRelationList()
 * @method array viewModelHasRelationList()
 * @method LengthAwarePaginator prepareViewModelHasRelationPaginate(PaginateData $paginate_dto)
 * @method array viewModelHasRelationPaginate(?PaginateData $paginate_dto = null)
 * @method array storeModelHasRelation(?ModelHasRelationData $model_has_relation_dto = null)
 * @method Collection prepareStoreMultipleModelHasRelation(array $datas)
 * @method array storeMultipleModelHasRelation(array $datas)
 */

interface ModelHasRelation extends DataManagement
{
    public function prepareStoreModelHasRelation(ModelHasRelationData $model_has_relation_dto): Model;
}