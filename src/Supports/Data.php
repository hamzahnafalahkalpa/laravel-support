<?php

namespace Zahzah\LaravelSupport\Supports;

use Spatie\LaravelData\Data as SpatieData;
use Zahzah\LaravelSupport\Concerns\Support\HasArray;
use Zahzah\LaravelSupport\Concerns\Support\HasConfigDatabase;

class Data extends SpatieData
{
    use HasArray, HasConfigDatabase;

    public function findFromVariadic(string $class, ...$args){
        $filters = array_filter($args, function ($arg) use ($class) {
            if (is_object($arg) && $arg::class == $class) {
                return $arg;
            }
        });
        return (count($filters) == 1) ? end($filters) : null;
    }

    public function callCustomMethod(): array{
        return ['Model'];
    }
}
