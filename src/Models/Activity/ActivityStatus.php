<?php

namespace Zahzah\LaravelSupport\Models\Activity;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Relation;
use Zahzah\LaravelHasProps\Concerns\HasProps;
use Zahzah\LaravelSupport\Models\BaseModel;

class ActivityStatus extends BaseModel
{   
    use HasUlids;

    CONST STATUS_ACTIVE_ON          = 1;
    CONST STATUS_ACTIVE_OFF         = 0;    
    public static $__activity;
    public $incrementing            = false;
    public $incrementTime           = 1;
    protected $table                = "activity_statuses";
    protected $keyType              = "string";
    protected $fillable             = [
      'id','activity_id','flag','status','active','message',
      'author_id','author_type'
    ];

    //BOOTED SECTION
    protected static function booted(): void{
        self::$__activity = app(config('database.models.Activity'));
        parent::booted();

        static::creating(function($q){
            if (!isset($q->active))  $q->active = self::STATUS_ACTIVE_ON;
            $created_at        = \microtime(true);
            $q->created_at     = $created_at;
            $q->updated_at     = \microtime(true);

            $activity           = self::$__activity->find($q->activity_id);
            static::$__activity = &$activity;

            $activity_message          = $q->getActivityMessage();
            $q->message                = $activity_message['message'];
            $activity->message         = $q->message;
            $activity->activity_status = $q->status;
            $existing_flag             = $activity->flag ?? []; 
            $activity->setAttribute('flag',(object) array_merge($existing_flag,[
                $activity_message['flag'] => [
                    'status'  => $q->status,
                    'message' => $q->message,
                    'at'      => date("Y-m-d H:i:s", $created_at)
                ]
            ]));
            $activity->save();
        });

        self::created(function($q){
            //OTHER ACTIVITY IN SAME FLAG
            self::where([
                ["id","<>",$q->id],
                ["activity_id",$q->activity_id],
                ["active",self::STATUS_ACTIVE_ON]
            ])->update(["active"=>self::STATUS_ACTIVE_OFF]);
        });

        self::updating(function($q){
            //GET STATUS MESSAGE
            $q->message = $q->getActivityMessage();
            self::$__activity = self::$__activity->where("id",$q->activity_id)->update([
                "message"         => $q->message,
                "activity_status" => $q->status
            ]);
        });
    }
    //END BOOTED SECTION

    //MUTATOR SECTION
    public function getActivityMessage($messageCode=null){
      $activity    = self::$__activity;
      $relation    = Relation::morphMap()[$activity->reference_type];
      $messageCode = $messageCode ?? $activity->activity_flag.'_'.$this->status;
      $model       = new $relation;
      return $model::$activityList[$messageCode];
    }
    //END MUTATOR SECTION

    //LOCAL SCOPE SECTION
    public function scopeActive($builder){return $builder->where('active',self::STATUS_ACTIVE_ON);}
    //END LOCAL SCOPE SECTION

    //EIGER SECTION
    public function reference(){return $this->morphTo(__FUNCTION__,"reference_type","reference_id");}
    public function author(){return $this->morphTo(__FUNCTION__,'author_type','author_id');}
    //END EIGER SECTION
}
