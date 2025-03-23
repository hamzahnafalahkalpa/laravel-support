<?php

namespace Hanafalah\LaravelSupport\Supports;

use Spatie\LaravelData\Data as SpatieData;
use Hanafalah\LaravelSupport\Concerns\Support\HasArray;
use Hanafalah\LaravelSupport\Concerns\Support\HasConfigDatabase;

class Data extends SpatieData
{
    use HasArray, HasConfigDatabase;

    protected object $__data;

    public function __construct()
    {
        if (\method_exists($this,'initializeProps')){
            $this->setProps($this->initializeProps($this->props ?? request()->props));
        }
    }

    public function findFromVariadic(string $class, ...$args)
    {
        $filters = array_filter($args, function ($arg) use ($class) {
            if (is_object($arg) && $arg::class == $class) {
                return $arg;
            }
        });
        return (count($filters) == 1) ? end($filters) : null;
    }

    protected function setProps(array|callable $callback): void{
        $this->props = (\is_array($callback)) ? $callback : $callback();
    }

    public function getProps(): array{
        return $this->props ?? [];
    }


    public function callCustomMethod(): array
    {
        return ['Model'];
    }
}
