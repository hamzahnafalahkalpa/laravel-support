<?php

namespace Zahzah\LaravelSupport\Models;

use Zahzah\LaravelSupport\Concerns as SupportConcerns;
use Zahzah\LaravelHasProps\Concerns as PropsConcerns;
use Zahzah\LaravelHasProps\Concerns\HasConfigProps;
use Illuminate\Support\Str;
use Projects\Klinik\Models\ModelHasRelation\ModelHasRelation;
use Zahzah\LaravelHasProps\Models\Scopes\HasCurrentScope;
use Zahzah\LaravelSupport\Concerns\Support\HasSoftDeletes;

class SupportBaseModel extends AbstractModel
{
    use HasConfigProps;
    use SupportConcerns\Support\HasDatabase;
    use SupportConcerns\Support\HasRepository;

    const STATUS_ACTIVE     = 1;
    const STATUS_DELETED    = 0;

    public $incrementing    = true;
    public $timestamps      = true;
    public $scopeLists      = [];
    public $lengthId        = 26; //PURPOSE STRING ONLY
    protected $primaryKey   = 'id';
    protected $keyType      = "int";
    protected $list         = [];
    protected $show         = [];
    protected static function booted(): void{
        parent::booted();
        static::addGlobalScope(new HasCurrentScope);
        static::creating(function($query){
            PropsConcerns\HasCurrent::currentChecking($query);

            if(self::isSetUuid($query) && !isset($query->{$query->getUuidName()})) {
                $query->uuid = Str::orderedUuid();
            }
        });
        static::created(function($query){
            static::withoutEvents(function () use ($query) {
                PropsConcerns\HasCurrent::setOld($query);
            });
        });
        static::updated(function($query){
            static::withoutEvents(function () use ($query) {
                if (!$query->wasRecentlyCreated && $query->isDirty('current')) PropsConcerns\HasCurrent::setOld($query);
            });
        });
        static::deleting(function($query){
            if(method_exists($query,'hasSoftDeletes') && !$query->forceDeleting){
                if ($query->hasSoftDeletes()){
                    HasSoftDeletes::softDeleting($query,static::new()->SoftDeleteModel());
                }
            }
        });
    }

    protected function casts(){
        if ($this->timestamps){
            return [
                'created_at' => 'datetime',
                'updated_at' => 'datetime'
            ];
        }
        return [];
    }

    public function getObserverExceptions(): array{
        return [];
    }

    public function toViewApi(){
        if (\method_exists($this,'getViewResource')) return (new $this->getViewResource())($this);
        
        return [];
    }

    public function toShowApi(){
        if (\method_exists($this,'getShowResource')){
            return (new $this->getShowResource())($this);
        }
        return [];
    }

    //MUTATOR SECTION
    public static function getTableName(){
        return with(static::new())->getTable();
    }

    //METHOD SECTION
    protected function validatingHistory($query){
        $validation = $query->getModel() <> $this->LogHistoryModel()::class;
        return $validation;
    }
    //END METHOD SECTION


    //EIGER SECTION
    public function activity(){return $this->morphOneModel('Activity','reference');}
    public function activities(){return $this->morphManyModel('Activity','reference');}
    public function modelHasRelation(){return $this->morphOneModel('ModelHasRelation','model');}
    public function modelHasRelations(){return $this->morphManyModel('ModelHasRelation','model');}
    //END EIGER SECTION
}
