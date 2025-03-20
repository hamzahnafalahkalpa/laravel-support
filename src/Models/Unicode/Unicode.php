<?php

namespace Hanafalah\LaravelSupport\Models\Unicode;

use Hanafalah\LaravelHasProps\Concerns\HasProps;
use Hanafalah\LaravelSupport\Models\BaseModel;

class Unicode extends BaseModel
{
    use HasProps;

    protected $table    = 'unicodes';
    public $timestamps  = false;
    protected $fillable = ['id', 'unicode_type', 'flag', 'name'];
}
