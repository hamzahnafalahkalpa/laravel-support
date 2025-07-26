<?php

namespace Hanafalah\LaravelSupport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;

abstract class AbstractModel extends Model
{
    use Macroable {
        __call as macroCall;
        __callStatic as macroCallStatic;
    }

    public function __call($method, $parameters)
    {
        if ($this->hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }

    public static function __callStatic($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return static::macroCallStatic($method, $parameters);
        }

        return parent::__callStatic($method, $parameters);
    }

    /**
     * Logs the histories.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany The morphMany relationship with LogHistory.
     */
    public function logHistories()
    {
        return $this->morphMany($this->LogHistoryModel(), "reference");
    }

    abstract public function parent();

    abstract public function child();
}
