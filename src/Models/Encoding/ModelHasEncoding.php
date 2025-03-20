<?php

namespace Hanafalah\LaravelSupport\Models\Encoding;

use Hanafalah\LaravelHasProps\Concerns\HasProps;
use Hanafalah\LaravelSupport\Models\BaseModel;

class ModelHasEncoding extends BaseModel
{
    use HasProps;

    protected $list = ['id', 'reference_id', 'reference_type', 'encoding_id', 'value', 'props'];
    public function reference()
    {
        return $this->morphTo();
    }
    public function encoding()
    {
        return $this->belongsToModel('Encoding');
    }
}
