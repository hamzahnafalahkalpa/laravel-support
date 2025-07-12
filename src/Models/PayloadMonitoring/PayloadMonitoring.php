<?php

namespace Hanafalah\LaravelSupport\Models\PayloadMonitoring;

use Hanafalah\LaravelSupport\Models\{
    BaseModel
};

class PayloadMonitoring extends BaseModel
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'string';

    protected $fillable = [
        'id',
        'url',
        'start_at',
        'end_at',
        'time_difference',
        'speed_category'
    ];
}
