<?php

namespace Hanafalah\LaravelSupport\Models\Elasticsearch;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Hanafalah\LaravelHasProps\Concerns\HasProps;
use Hanafalah\LaravelSupport\Models\BaseModel;
use Illuminate\Support\Str;

class ElasticsearchLog extends BaseModel
{
  use HasUlids, HasProps;

  public $incrementing  = false;
  protected $primaryKey = 'id';
  protected $keyType    = 'string';
  protected $fillable   = [
    'id',
    'name',
    'reference_type',
    'reference_id',
    'synced_at',
    'props',
    'created_at',
    'updated_at'
  ];

  public function reference(){return $this->morphTo();}
}
