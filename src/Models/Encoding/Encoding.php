<?php

namespace Zahzah\LaravelSupport\Models\Encoding;

use Zahzah\LaravelSupport\Models\BaseModel;

class Encoding extends BaseModel {
    protected $list = ['id','name','flag'];
    public function modelHasEncoding(){return $this->hasOneModel('ModelHasEncoding');}
}