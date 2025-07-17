<?php

namespace Hanafalah\LaravelSupport\Models\PayloadMonitoring;

use Hanafalah\LaravelSupport\Models\{
    BaseModel
};
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class PayloadMonitoring extends BaseModel
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'url',
        'start_at',
        'end_at',
        'time_difference',
        'speed_category'
    ];
}
