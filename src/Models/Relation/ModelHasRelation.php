<?php

namespace Zahzah\LaravelSupport\Models\Relation;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Zahzah\LaravelSupport\Models\BaseModel;

class ModelHasRelation extends BaseModel{
    use HasUlids, SoftDeletes;

    public $incrementing   = false;
    public $timestamps     = true;
    protected $parimaryKey = 'id';
    protected $keyType     = 'string';
    protected $table       = 'model_has_relations';
    protected $fillable    = [
        'id','model_type','model_id',
        'relation_type','relation_id','props'
    ];

    //EIGER SECTION
    public function model(){return $this->morphTo();}
    public function relation(){return $this->morphTo();}
    //END EIGER SECTION
}