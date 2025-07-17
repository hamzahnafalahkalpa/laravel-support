<?php

namespace Hanafalah\LaravelSupport\Models;

use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

class PivotBaseModel extends BaseModel
{
    use AsPivot;

    public $incrementing = false;
}
