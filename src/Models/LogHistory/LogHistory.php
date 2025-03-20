<?php

namespace Zahzah\LaravelSupport\Models\LogHistory;

use Zahzah\LaravelSupport\Models;

class LogHistory extends Models\BaseModel
{
    CONST ACTION_INSERT      = "INSERT";
    CONST ACTION_UPDATE      = "UPDATE";
    CONST ACTION_DELETE      = "DELETE";
    CONST ACTION_SOFT_DELETE = "SOFT_DELETE";
    CONST CONTENT_TYPE_JSON  = "JSON";
    public $incrementing     = false;
    protected $table         = "log_histories";
    protected $primaryKey    = "id";
    protected $keyType       = 'string';
    protected $fillable      = [
      "id","reference_id","reference_type",
      "content","content_type","action_type",
      "author_id","author_type","author_name"      
    ];
    protected static function booted(): void{
      self::creating(function($query){
        if (!isset($query->id)) $query->id = $query->getNewId();
      });
    }
    public function refHistory(){return $this->morphTo(__FUNCTION__,'reference_type','reference_id');}
}