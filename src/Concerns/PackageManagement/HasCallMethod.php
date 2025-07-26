<?php

namespace Hanafalah\LaravelSupport\Concerns\PackageManagement;

use Hanafalah\LaravelSupport\Concerns\Support\HasCall;
use Hanafalah\LaravelSupport\Contracts\Data\PaginateData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

trait HasCallMethod
{
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
