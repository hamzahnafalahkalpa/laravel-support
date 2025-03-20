<?php

namespace Zahzah\LaravelSupport\Concerns\Support;

use Illuminate\Database\Eloquent\SoftDeletes;
use Zahzah\LaravelSupport\Facades\LaravelSupport;

trait HasSoftDeletes{
    use SoftDeletes;

    protected function hasSoftDeletes(){
        return true;
    }

    /**
     * Soft delete a record and save it to a separate table
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model   $soft_delete_model
     *
     * @return void
     */
    public static function softDeleting($query,$soft_delete_model){
        $attributes  = $query->getOriginal();
        foreach ($attributes as $key => $attribute) {
            $soft_delete_model->{$key} = $attribute;
        }
        $soft_delete_model->user_info  = LaravelSupport::getUserInfo();
        $soft_delete_model->deleted_at = now();
        $soft_delete_model->save();

        $query->forceDelete();
    }
}