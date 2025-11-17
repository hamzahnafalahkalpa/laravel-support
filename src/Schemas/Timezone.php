<?php

namespace Hanafalah\LaravelSupport\Schemas;

use Illuminate\Database\Eloquent\Model;
use Hanafalah\LaravelSupport\Contracts\Schemas\Timezone as ContractsTimezone;
use Hanafalah\LaravelSupport\Contracts\Data\TimezoneData;
use Illuminate\Database\Eloquent\Builder;

class Timezone extends Unicode implements ContractsTimezone
{
    protected string $__entity = 'Timezone';
    public $timezone_model;
    //protected mixed $__order_by_created_at = false; //asc, desc, false

    protected array $__cache = [
        'index' => [
            'name'     => 'timezone',
            'tags'     => ['timezone', 'timezone-index'],
            'duration' => 24 * 60
        ]
    ];

    public function prepareStoreTimezone(TimezoneData $timezone_dto): Model{
        $timezone = $this->prepareStoreUnicode($timezone_dto);
        return $this->timezone_model = $timezone;
    }

    public function timezone(mixed $conditionals = null): Builder{
        return $this->unicode($conditionals);
    }
}