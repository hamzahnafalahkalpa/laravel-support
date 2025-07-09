<?php

namespace Hanafalah\LaravelSupport\Models\Relation;

use Hanafalah\LaravelHasProps\Concerns\HasProps;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Hanafalah\LaravelSupport\Models\BaseModel;
use Hanafalah\LaravelSupport\Resources\ModelHasRelation\{
    ViewModelHasRelation,
    ShowModelHasRelation
};

class ModelHasRelation extends BaseModel
{
    use HasUlids, SoftDeletes, HasProps;

    public $incrementing   = false;
    public $timestamps     = true;
    protected $parimaryKey = 'id';
    protected $keyType     = 'string';
    protected $table       = 'model_has_relations';
    protected $fillable    = [
        'id',
        'model_type',
        'model_id',
        'relation_type',
        'relation_id',
        'props'
    ];

    public function viewUsingRelation(): array{
        return [];
    }

    public function showUsingRelation(): array{
        return [];
    }

    public function getViewResource(){return ViewModelHasRelation::class;}
    public function getShowResource(){return ShowModelHasRelation::class;}
    //EIGER SECTION
    public function model(){return $this->morphTo();}
    public function relation(){return $this->morphTo();}
    //END EIGER SECTION
}
