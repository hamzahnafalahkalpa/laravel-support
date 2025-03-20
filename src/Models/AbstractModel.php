<?php 

namespace Zahzah\LaravelSupport\Models;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractModel extends Model{
    /**
    * Logs the histories.
    *
    * @return \Illuminate\Database\Eloquent\Relations\MorphMany The morphMany relationship with LogHistory.
    */
    public function logHistories(){
        return $this->morphMany($this->LogHistoryModel(),"reference");       
    }  

    abstract public function parent();

    abstract public function child();
}