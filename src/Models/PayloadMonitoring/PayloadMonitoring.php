<?php

namespace Hanafalah\LaravelSupport\Models\PayloadMonitoring;

use Hanafalah\LaravelSupport\Models\{
    BaseModel
};

class PayloadMonitoring extends BaseModel
{
    protected $fillable = [
        'url',
        'start_at',
        'end_at',
        'time_difference',
        'speed_category'
    ];
}
