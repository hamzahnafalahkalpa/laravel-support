<?php

namespace Hanafalah\LaravelSupport\Schemas;

use Hanafalah\LaravelSupport\Supports\PackageManagement;
use Hanafalah\LaravelSupport\Contracts\Data\UnicodeData;
use Hanafalah\LaravelSupport\Contracts\Schemas\Unicode as ContractsUnicode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Unicode extends PackageManagement implements ContractsUnicode
{
    protected string $__entity = 'Unicode';
    public static $unicode_model;
    protected mixed $__order_by_created_at = ['ordering','asc']; //asc, desc, false

    protected array $__cache = [
        'index' => [
            'name'     => 'unicode',
            'tags'     => ['unicode', 'unicode-index'],
            'forever'  => true
        ]
    ];

    public function prepareStoreUnicode(UnicodeData $unicode_dto): Model{            
        $add = [
            'parent_id' => $unicode_dto->parent_id ?? null,
            'name' => $unicode_dto->name,
            'flag' => $unicode_dto->flag,
            'label' => $unicode_dto->label,
            'status' => $unicode_dto->status,
            'ordering' => $unicode_dto->ordering ?? 1,
        ];
        if (isset($unicode_dto->id)){
            $guard = ['id' => $unicode_dto->id];
            $create = [$guard,$add];
        }else{
            $create = [$add];
        }
        $unicode = $this->usingEntity()->updateOrCreate(...$create);
        if (isset($unicode_dto->childs) && count($unicode_dto->childs) > 0){
            foreach ($unicode_dto->childs as $child){
                $child->parent_id = $unicode->getKey();
                $child->flag      = $unicode->flag;
                $this->prepareStoreUnicode($child);
            }
        }

        $this->forgetTags('unicode');
        return static::$unicode_model = $unicode;
    }

    public function unicode(mixed $conditionals = null): Builder{
        return $this->generalSchemaModel()->whereNull('parent_id');
    }
}
