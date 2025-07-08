<?php

namespace Hanafalah\LaravelSupport\Data;

use Hanafalah\LaravelSupport\Contracts\Data\ModelHasRelationData as DataModelHasRelationData;
use Hanafalah\LaravelSupport\Supports\Data;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;

class ModelHasRelationData extends Data implements DataModelHasRelationData
{
    #[MapInputName('id')]
    #[MapName('id')]
    public mixed $id = null;

    #[MapInputName('model_type')]
    #[MapName('model_type')]
    public string $model_type;

    #[MapInputName('model_id')]
    #[MapName('model_id')]
    public mixed $model_id = null;

    #[MapInputName('relation_type')]
    #[MapName('relation_type')]
    public string $relation_type;

    #[MapInputName('relation_id')]
    #[MapName('relation_id')]
    public mixed $relation_id;

    #[MapInputName('props')]
    #[MapName('props')]
    public ?array $props = null;

    public static function after(self $data): self{
        $new = self::new();
        $props = &$data->props;

        $model = $new->{$data->model_type.'Model'}()->findOrFail($data->model_id);
        $props['prop_model'] = $model->toViewApi()->resolve();

        $relation = $new->{$data->relation_type.'Model'}()->findOrFail($data->relation_id);
        $props['prop_relation'] = $relation->toViewApi()->resolve();
        return $data;
    }
}