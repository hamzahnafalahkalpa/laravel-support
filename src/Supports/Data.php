<?php

namespace Hanafalah\LaravelSupport\Supports;

use Hanafalah\LaravelSupport\Concerns\DatabaseConfiguration\HasModelConfiguration;
use Spatie\LaravelData\Data as SpatieData;
use Hanafalah\LaravelSupport\Concerns\Support\HasArray;
use Hanafalah\LaravelSupport\Concerns\Support\HasConfigDatabase;
use Hanafalah\LaravelSupport\Concerns\Support\HasRequestData;
use Illuminate\Database\Eloquent\Model;

class Data extends SpatieData
{
    use HasArray, HasModelConfiguration, HasRequestData;

    protected object $__data;

    public function findFromVariadic(string $class, ...$args)
    {
        $filters = array_filter($args, function ($arg) use ($class) {
            if (is_object($arg) && $arg::class == $class) {
                return $arg;
            }
        });
        return (count($filters) == 1) ? end($filters) : null;
    }

    protected static function new(){
        return (new static);
    }

    public function callCustomMethod(): array
    {
        return ['Model'];
    }

    protected function fillMissingFromModel(self $data, Model $model, array $attributes): self{
        foreach ($attributes as $field) if (blank($data->{$field})) $data->{$field} ??= $model->{$field};
        return $data;
    }

    public static function before(array &$attributes){

    }

}
