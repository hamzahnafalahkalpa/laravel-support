<?php

namespace Hanafalah\LaravelSupport\Facades;

use Illuminate\Support\Facades\Facade;
use Hanafalah\LaravelSupport\Contracts\LaravelSupport as ContractsLaravelSupport;

/**
 * @method static void exceptionRespond(Exceptions $exceptions)
 */
class LaravelSupport extends Facade
{

   protected static function getFacadeAccessor()
   {
      return ContractsLaravelSupport::class;
   }
}
