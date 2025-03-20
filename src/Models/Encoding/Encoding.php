<?php

namespace Hanafalah\LaravelSupport\Models\Encoding;

use Hanafalah\LaravelSupport\Models\BaseModel;

class Encoding extends BaseModel
{
    protected $list = ['id', 'name', 'flag'];
    public function modelHasEncoding()
    {
        return $this->hasOneModel('ModelHasEncoding');
    }
}
