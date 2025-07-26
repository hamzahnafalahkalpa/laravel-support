<?php

namespace Hanafalah\LaravelSupport\Models\LogHistory;

use Hanafalah\LaravelSupport\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class LogHistory extends Models\BaseModel
{
  use HasUlids;

  const ACTION_INSERT      = "INSERT";
  const ACTION_UPDATE      = "UPDATE";
  const ACTION_DELETE      = "DELETE";
  const ACTION_SOFT_DELETE = "SOFT_DELETE";
  const CONTENT_TYPE_JSON  = "JSON";
  public $incrementing     = false;
  protected $table         = "log_histories";
  protected $primaryKey    = "id";
  protected $keyType       = 'string';
  protected $fillable      = [
    "id",
    "reference_id",
    "reference_type",
    "content",
    "content_type",
    "action_type",
    "author_id",
    "author_type",
    "author_name"
  ];
  protected static function booted(): void
  {
    self::creating(function ($query) {
      if (!isset($query->id)) $query->id = $query->getNewId();
    });
  }
  public function refHistory()
  {
    return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
  }
}
