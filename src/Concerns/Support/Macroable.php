<?php
namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Support\Traits\Macroable as TraitsMacroable;

trait Macroable{
    use TraitsMacroable {
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
}