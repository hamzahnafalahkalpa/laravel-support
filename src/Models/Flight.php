<?php

namespace Hanafalah\LaravelSupport\laravel-support\Models\Flight;

use Illuminate\Database\Eloquent\SoftDeletes;
use Hanafalah\LaravelHasProps\Concerns\HasProps;
use Hanafalah\LaravelSupport\Models\BaseModel;
use Hanafalah\LaravelSupport\laravel-support\Resources\Flight\{
    ViewFlight,
    ShowFlight
};

class Flight extends BaseModel
{
    use HasProps, SoftDeletes;
    
    public $list = [[
        '0' => 'id',
        '1' => 'props',
    ]];

    protected $casts = [];

    

    public function viewUsingRelation(): array{
        return [];
    }

    public function showUsingRelation(): array{
        return [];
    }

    public function getViewResource(){
        return ViewFlight::class;
    }

    public function getShowResource(){
        return ShowFlight::class;
    }

    

    
}
