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
    public $unicode_model;
    protected mixed $__order_by_created_at = ['ordering','asc']; //asc, desc, false

    protected array $__cache = [
        'index' => [
            'name'     => 'unicode',
            'tags'     => ['unicode', 'unicode-index'],
            'forever'  => true
        ]
    ];

    protected function isIdAsPrimaryValidation(): bool{
        return false;
    }

    public function prepareStoreUnicode(UnicodeData $unicode_dto): Model{            
        $add = [
            'parent_id' => $unicode_dto->parent_id ?? null,
            'name'      => $unicode_dto->name,
            'flag'      => $unicode_dto->flag,
            'label'     => $unicode_dto->label,
            'status'    => $unicode_dto->status,
            'ordering'  => $unicode_dto->ordering ?? 1,
        ];
        if ($this->isIdAsPrimaryValidation()){
            $unicode = $this->usingEntity()->updateOrCreate([
                'id' => $unicode_dto->id ?? null
            ],$add);
        }else{
            if (isset($unicode_dto->id)){
                $guard  = ['id' => $unicode_dto->id];
                $create = [$guard,$add];
            }else{
                $create = [$add];
            }
            $unicode = $this->usingEntity()->firstOrCreate(...$create);
        }
        if (isset($unicode_dto->childs) && count($unicode_dto->childs) > 0){
            $ordering = 1;
            foreach ($unicode_dto->childs as $child){
                $child->parent_id = $unicode->getKey();
                $child->flag      = $unicode->flag;
                $child->ordering ??= $ordering++;
                $this->prepareStoreUnicode($child);
            }
        }

        if (isset($unicode_dto->service)){
            $service_dto = &$unicode_dto->service;
            $service_dto->reference_id ??= $unicode->getKey();
            $service = $this->schemaContract('Service')->prepareStoreService($service_dto);
            $unicode_dto->props['prop_service'] = $service->toViewApi()->resolve();
        }

        $this->fillingProps($unicode, $unicode_dto->props);
        $unicode->save();
        $this->forgetTags('unicode');
        return $this->unicode_model = $unicode;
    }

    public function unicode(mixed $conditionals = null): Builder{
        return parent::generalSchemaModel($conditionals)->when(isset(request()->flag),function($query){
            return $query->flagIn(request()->flag);
        })->whereNull('parent_id');
    }

    //OVERIDING DATA MANAGEMENT
    public function generalPrepareStore(mixed $dto = null): Model{
        if (is_array($dto)) $dto = $this->requestDTO(config("app.contracts.{$this->__entity}Data",null));
        $model = $this->prepareStoreUnicode($dto);
        return $this->entityData($model);
    }

    public function generalSchemaModel(mixed $conditionals = null): Builder{
        return $this->unicode($conditionals);
    }
}
