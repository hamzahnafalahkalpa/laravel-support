<?php

namespace Zahzah\LaravelSupport\Concerns\PackageManagement;

use Zahzah\LaravelSupport\Concerns\Support\HasCall;

use Illuminate\Support\Str;

trait HasCallMethod{
    use HasCall;

    /**
     * Calls the custom method for the current instance.
     *
     * It will first check if the method is a custom method, and if so, it will
     * call the method with the given arguments.
     *
     * @return mixed|null
     */
    public function __callMethod(){
        $method = $this->getCallMethod();
        if (Str::startsWith($method, 'call') && Str::endsWith($method, 'Method')) {
            $key = Str::between($method, 'call', 'Method');
            if (!method_exists($this, $key)) {
                return $this->{$key}($this->getCallArguments());
            }
        }
    }
}