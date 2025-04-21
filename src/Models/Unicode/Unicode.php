<?php

namespace Hanafalah\LaravelSupport\Models\Unicode;

use Hanafalah\LaravelHasProps\Concerns\HasProps;
use Hanafalah\LaravelSupport\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unicode extends BaseModel
{
    use HasProps, SoftDeletes;

    protected $table    = 'unicodes';
    protected $fillable = ['id', 'flag', 'name', 'props'];

    public function viewUsingRelation():array {
        return [];
    }

    public function showUsingRelation():array {
        return [];
    }
}
