<?php
namespace Hanafalah\LaravelSupport\Supports;

use Illuminate\Database\Eloquent\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
    public function updateOrCreate(array $attributes, array $values = [])
    {
        if (array_key_exists('id', $attributes) && is_null($attributes['id'])) {
            unset($attributes['id']);
            return $this->create($values);
        }

        return parent::updateOrCreate($attributes, $values);
    }
}