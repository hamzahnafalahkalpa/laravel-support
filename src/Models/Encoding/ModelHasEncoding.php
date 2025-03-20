<?php

namespace Zahzah\LaravelSupport\Models\Encoding;

use Zahzah\LaravelHasProps\Concerns\HasProps;
use Zahzah\LaravelSupport\Models\BaseModel;

class ModelHasEncoding extends BaseModel {
    use HasProps;

    protected $list = ['id','reference_id','reference_type','encoding_id','value','props']; 
    public function reference(){return $this->morphTo();}
    public function encoding(){return $this->belongsToModel('Encoding');}
}