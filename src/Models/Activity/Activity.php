<?php

namespace Hanafalah\LaravelSupport\Models\Activity;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Hanafalah\LaravelHasProps\Concerns\HasProps;
use Hanafalah\LaravelSupport\Models\BaseModel;
use Illuminate\Support\Str;

class Activity extends BaseModel
{
  use HasUlids, HasProps;

  public $incrementing  = false;
  protected $table      = 'activities';
  protected $primaryKey = 'id';
  protected $keyType    = 'string';
  protected $fillable   = [
    'id',
    'activity_flag',
    'reference_type',
    'reference_id',
    'props',
    'status',
    'activity_status',
    'message',
    'created_at',
    'updated_at'
  ];

  //BOOTED SECTION
  protected static function booted(): void
  {
    parent::booted();
    self::creating(function ($q) {
      if (!isset($q->status)) $q->status = self::STATUS_ACTIVE;
    });
    self::updating(function ($q) {
      $q->load([
        'reference' => function ($query) {
          $query->withoutGlobalScopes();
        }
      ]);
      $reference = $q->reference;
      //FOR PROPS
      if (\method_exists($reference, 'getDataColumn')) {
        $prop_activity = $reference->prop_activity ?? [];
        $prop_activity[Str::snake($q->activity_flag)] = $q->flag;
        $reference->setAttribute('prop_activity', (object) $prop_activity);
        $reference->save();
      }
    });
  }
  //END BOOTED SECTION

  //EIGER SECTION
  public function reference()
  {
    return $this->morphTo(__FUNCTION__);
  }
  public function activityStatus()
  {
    return $this->hasOneModel('ActivityStatus');
  }
  public function activityStatuses()
  {
    return $this->hasManyModel('ActivityStatus');
  }
  //END EIGER SECTION
}
