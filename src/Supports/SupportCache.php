<?php

namespace Hanafalah\LaravelSupport\Supports;

use Hanafalah\LaravelSupport\Concerns\Support\HasCache;
use Hanafalah\LaravelSupport\Contracts\Supports\SupportCache as SupportsSupportCache;

class SupportCache implements SupportsSupportCache{
    use HasCache;

    protected array $__cache_datas = [];

    public function saveCache(string $name, mixed $data): void{
        $this->__cache_datas[$name] = $data;
    }

    public function getSavedCache(string $name): mixed{
        return $this->__cache_datas[$name] ?? null;
    }
}