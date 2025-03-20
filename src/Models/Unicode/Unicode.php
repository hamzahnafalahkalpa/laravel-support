<?php

namespace Zahzah\LaravelSupport\Models\Unicode;

use Zahzah\LaravelHasProps\Concerns\HasProps;
use Zahzah\LaravelSupport\Models\BaseModel;

class Unicode extends BaseModel{
    use HasProps;

    protected $table    = 'unicodes';
    public $timestamps  = false;
    protected $fillable = ['id','unicode_type','flag','name'];
}