<?php

namespace Hanafalah\LaravelSupport\Supports;

use Hanafalah\LaravelSupport\Concerns\Support\HasCache;
use Hanafalah\LaravelSupport\Contracts\Supports\SupportCache as SupportsSupportCache;
use Illuminate\Cache\CacheManager;

class SupportCache extends CacheManager implements SupportsSupportCache{
    use HasCache;
}