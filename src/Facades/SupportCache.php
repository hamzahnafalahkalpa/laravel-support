<?php

namespace Hanafalah\LaravelSupport\Facades;

use Hanafalah\LaravelSupport\Supports\SupportCache as SupportsSupportCache;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void exceptionRespond(Exceptions $exceptions)
 */
class SupportCache extends Facade
{

   protected static function getFacadeAccessor()
   {
      return SupportsSupportCache::class;
   }
}
