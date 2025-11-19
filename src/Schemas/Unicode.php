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
    protected bool $__is_parent_only = true;

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
            'status'    => $unicode_dto->status,
            'ordering'  => $unicode_dto->ordering ?? 1,
        ];
        if ($this->isIdAsPrimaryValidation()){
            if (!isset($unicode_dto->id)){
                $add = array_merge($add,[
                    'flag'      => $unicode_dto->flag,
                    'label'     => $unicode_dto->label,
                ]);
            }
            $unicode = $this->usingEntity()->withoutGlobalScopes()->updateOrCreate([
                'id' => $unicode_dto->id ?? null
            ],$add);
        }else{
            if (isset($unicode_dto->id)){
                $guard  = ['id' => $unicode_dto->id];
                $create = [$guard,$add];
            }else{
                $add = array_merge($add,[
                    'flag'      => $unicode_dto->flag,
                    'label'     => $unicode_dto->label,
                ]);
                $create = [$add];
            }
            $unicode = $this->usingEntity()->withoutGlobalScopes()->updateOrCreate(...$create);
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

        $service_dto = &$unicode_dto->service;
        if (!isset($service_dto) && method_exists($unicode, 'isUsingService') && $unicode->isUsingService()){
            $service_dto = $this->requestDTO(config('app.contracts.ServiceData'),[
                'reference_id'   => $unicode->getKey(),
                'reference_type' => $unicode->flag,
                'name'           => $unicode->name,
                'price'          => $unicode->price ?? 0
            ]);
        }
        if (isset($service_dto)){
            $service_dto->reference_id ??= $unicode->getKey();
            $service_dto->reference_type ??= $unicode->flag;
            $service_dto->name ??= $unicode->name;
            $service_dto->price ??= $unicode->price ?? 0;
            $service = $this->schemaContract('Service')->prepareStoreService($service_dto);
            $unicode_dto->props['prop_service'] = $service->toViewApi()->resolve();
        }
        $this->fillingProps($unicode, $unicode_dto->props);
        $unicode->save();
        $this->forgetTags('unicode');
        return $this->unicode_model = $unicode;
    }

    public function setIsParentOnly(?bool $status = true):self{
        $this->__is_parent_only = $status ?? true;
        return $this;
    }

    private function getIsParentOnly():bool{
        return $this->__is_parent_only;
    }

    public function unicode(mixed $conditionals = null): Builder{
        return parent::generalSchemaModel($conditionals)->when(isset(request()->flag),function($query){
            return $query->flagIn(request()->flag);
        })->when($this->getIsParentOnly(),function($q){
            $q->whereNull('parent_id');
        });
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
