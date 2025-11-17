<?php

namespace Hanafalah\LaravelSupport\Models\Unicode;

use Hanafalah\LaravelSupport\Resources\Timezone\ShowTimezone;
use Hanafalah\LaravelSupport\Resources\Timezone\ViewTimezone;

class Timezone extends Unicode
{
    protected $table = 'unicodes';

    public function getViewResource(){return ViewTimezone::class;}
    public function getShowResource(){return ShowTimezone::class;}
}
