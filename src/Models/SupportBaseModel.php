<?php

namespace Hanafalah\LaravelSupport\Models;

use Hanafalah\LaravelSupport\Concerns as SupportConcerns;
use Hanafalah\LaravelHasProps\Concerns as PropsConcerns;
use Hanafalah\LaravelHasProps\Concerns\HasConfigProps;
use Illuminate\Support\Str;
use Hanafalah\LaravelHasProps\Models\Scopes\HasCurrentScope;
use Hanafalah\LaravelSupport\Concerns\Support\HasCache;
use Hanafalah\LaravelSupport\Supports\Builder;

class SupportBaseModel extends AbstractModel
{
    use HasConfigProps, HasCache;
    use SupportConcerns\Support\HasDatabase;
    use SupportConcerns\DatabaseConfiguration\HasModelConfiguration;
    use SupportConcerns\Support\HasConfigDatabase;
    use SupportConcerns\Support\HasRepository;

    const STATUS_ACTIVE     = 1;
    const STATUS_DELETED    = 0;

    public $incrementing    = true;
    public $timestamps      = true;
    public $scopeLists      = [];
    public $lengthId        = 26; //PURPOSE STRING ONLY
    protected $primaryKey   = 'id';
    protected $keyType      = "int";
    protected $list         = [];
    protected $show         = [];

    protected static function booted(): void
    {
        parent::booted();
        static::addGlobalScope(new HasCurrentScope);
        static::creating(function ($query) {
            PropsConcerns\HasCurrent::currentChecking($query);
            if (self::isSetUuid($query) && !isset($query->{$query->getUuidName()})) {
                $query->uuid = Str::orderedUuid();
            }
        });
        static::created(function ($query) {
            static::clearCacheModel($query);
            static::withoutEvents(function () use ($query) {
                PropsConcerns\HasCurrent::setOld($query);
            });
        });
        static::updated(function ($query) {
            static::clearCacheModel($query);
            static::withoutEvents(function () use ($query) {
                if (!$query->wasRecentlyCreated && $query->isDirty('current')) PropsConcerns\HasCurrent::setOld($query);
            });
        });
        static::deleting(function ($query) {
        });
    }

    private static function clearCacheModel($query){
        $morph = Str::snake($query->getMorphClass());
        $cacheDriver = config('cache.default');
        if ($cacheDriver === 'redis') {
            $query->forgetTags($morph);
        } else {
            $query->forgetKey($morph);
        }
    }

    public function newEloquentBuilder($query): Builder
    {
        return new Builder($query);
    }

    protected function casts()
    {
        if ($this->timestamps) {
            return [
                'created_at' => 'datetime',
                'updated_at' => 'datetime'
            ];
        }
        return [];
    }

    public function getObserverExceptions(): array
    {
        return [];
    }

    //MUTATOR SECTION
    public static function getTableName()
    {
        return with(static::new())->getTable();
    }

    //METHOD SECTION
    protected function validatingHistory($query)
    {
        $validation = $query->getModel() <> $this->LogHistoryModel()::class;
        return $validation;
    }
    //END METHOD SECTION

    public function getMorphClass(): string{
        return \class_basename(static::class);
    }

    //EIGER SECTION
    public function activity(){return $this->morphOneModel('Activity', 'reference');}
    public function activities(){return $this->morphManyModel('Activity', 'reference');}
    public function modelHasRelation(){return $this->morphOneModel('ModelHasRelation', 'model');}
    public function modelHasRelations(){return $this->morphManyModel('ModelHasRelation', 'model');}
    //END EIGER SECTION
}
