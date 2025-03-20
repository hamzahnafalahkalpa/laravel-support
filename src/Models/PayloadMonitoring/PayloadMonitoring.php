<?php

namespace Zahzah\LaravelSupport\Models\PayloadMonitoring;

use Zahzah\LaravelSupport\Models\{
    BaseModel
};

class PayloadMonitoring extends BaseModel
{
    protected $fillable = [
        'url','start_at','end_at','time_difference','speed_category'
    ];
}
