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
        $arguments = $this->getCallArguments() ?? [];

        if (Str::startsWith($method, 'call') && Str::endsWith($method, 'Method')) {
            $key = Str::between($method, 'call', 'Method');
            if (!method_exists($this, $key)) {
                return $this->{$key}($this->getCallArguments());
            }
        }

        if ($method !== 'show' && Str::startsWith($method, 'show'.$this->__entity)){
            return $this->generalShow(...$arguments);
        }

        if ($method !== 'prepareShow' && Str::startsWith($method, 'prepareShow'.$this->__entity)){
            return $this->generalPrepareShow(...$arguments);
        }

        if ($method !== 'prepareView' && Str::startsWith($method, 'prepareView'.$this->__entity) && Str::endsWith($method,'Paginate')){
            return $this->generalPrepareViewPaginate(...$arguments);
        }

        if ($method !== 'view' && Str::startsWith($method, 'view'.$this->__entity) && Str::endsWith($method,'Paginate')){
            return $this->generalViewPaginate();
        }

        if ($method !== 'prepareView' && Str::startsWith($method, 'prepareView'.$this->__entity) && Str::endsWith($method,'List')){
            return $this->generalPrepareViewList(...$arguments);
        }

        if ($method !== 'view' && Str::startsWith($method, 'view'.$this->__entity) && Str::endsWith($method,'List')){
            return $this->generalViewList();
        }

        if ($method !== 'generalFind' && Str::startsWith($method, 'generalFind'.$this->__entity)){
            return $this->generalPrepareFind(...$arguments);
        }

        if ($method == 'find'.$this->__entity){
            return $this->generalFind(...$arguments);
        }

        if ($method !== 'prepareDelete' && Str::startsWith($method, 'prepareDelete'.$this->__entity)){
            return $this->generalPrepareDelete(...$arguments);
        }

        if ($method !== 'delete' && Str::startsWith($method, 'delete'.$this->__entity)){
            return $this->generalDelete();
        }
        
        if ($method !== 'store' && Str::startsWith($method, 'store'.$this->__entity)){
            return $this->generalStore();
        }

        if (Str::startsWith($method, Str::camel($this->__entity))){
            return $this->generalSchemaModel();
        }
    }
}
