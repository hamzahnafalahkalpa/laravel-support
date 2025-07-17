<?php

namespace Hanafalah\LaravelSupport\Schemas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Hanafalah\LaravelSupport\{
    Supports\BaseLaravelSupport
};
use Hanafalah\LaravelSupport\Contracts\Schemas\ModelHasRelation as ContractsModelHasRelation;
use Hanafalah\LaravelSupport\Contracts\Data\ModelHasRelationData;
use Hanafalah\LaravelSupport\Supports\PackageManagement;

class ModelHasRelation extends PackageManagement implements ContractsModelHasRelation
{
    protected string $__entity = 'ModelHasRelation';
    public static $model_has_relation_model;
    //protected mixed $__order_by_created_at = false; //asc, desc, false

    protected array $__cache = [
        'index' => [
            'name'     => 'model_has_relation',
            'tags'     => ['model_has_relation', 'model_has_relation-index'],
            'duration' => 24 * 60
        ]
    ];

    public function prepareStoreModelHasRelation(ModelHasRelationData $model_has_relation_dto): Model{
        $add = [
            'model_type' => $model_has_relation_dto->model_type,
            'model_id'   => $model_has_relation_dto->model_id,
            'reference_type' => $model_has_relation_dto->reference_type,
            'reference_id'   => $model_has_relation_dto->reference_id
        ];
        if (isset($model_has_relation_dto->id)){
            $guard  = ['id' => $model_has_relation_dto->id];
            $create = [$guard, $add];
        }else{
            $create = [$add];
        }

        $model_has_relation = $this->usingEntity()->updateOrCreate(...$create);
        $this->fillingProps($model_has_relation,$model_has_relation_dto->props);
        $model_has_relation->save();
        return static::$model_has_relation_model = $model_has_relation;
    }
}