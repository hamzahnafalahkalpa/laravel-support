<?php

namespace Hanafalah\LaravelSupport\Models\Unicode;

use Hanafalah\LaravelHasProps\Concerns\HasProps;
use Hanafalah\LaravelSupport\Models\BaseModel;
use Hanafalah\LaravelSupport\Resources\Unicode\{ShowUnicode, ViewUnicode};
use Hanafalah\ModuleService\Concerns\{HasService,HasPriceComponent};
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unicode extends BaseModel
{
    use HasUlids, HasProps, SoftDeletes, 
        HasService, HasPriceComponent;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';
    protected $list = ['id', 'parent_id', 'name', 'flag', 'label', 'ordering', 'props'];

    protected static function booted(): void
    {
        parent::booted();
        static::addGlobalScope('flag',function($query){
            $query->where('flag',(new static)->getMorphClass());
        });
        static::creating(function ($query) {
            $query->flag ??= (new static)->getMorphClass();
        });
    }

    protected function isUsingService(): bool{
        return false;
    }

    public function viewUsingRelation():array {
        $relation = ['childs'];
        if ($this->isUsingService()){
            $relation[] = 'service';
        }
        return $relation;
    }

    public function showUsingRelation():array {
        $relation = ['childs'];
        if ($this->isUsingService()){
            $relation[] = 'service.priceComponents.tariffComponent';
        }
        return $relation;
    }

    public function getViewResource(){return ViewUnicode::class;}
    public function getShowResource(){return ShowUnicode::class;}

    public function scopeLabelIn($builder,string|array $labels){
        $labels = $this->mustArray($labels);
        return $builder->whereIn('label', $labels);
    }
    public function childs(){
        $builder = $this->hasManyModel((new static)->getMorphClass(), 'parent_id')->with('childs');
        if ($this->isUsingService()) $builder->with('service');
        return $builder;
    }    
}
