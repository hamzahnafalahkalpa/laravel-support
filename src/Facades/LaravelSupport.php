<?php 

namespace Zahzah\LaravelSupport\Facades;

use Illuminate\Support\Facades\Facade;
use Zahzah\LaravelSupport\Contracts\LaravelSupport as ContractsLaravelSupport;

/**
 * @method static void exceptionRespond(Exceptions $exceptions)
 */
class LaravelSupport extends Facade{

   protected static function getFacadeAccessor()
   {
      return ContractsLaravelSupport::class;
   }
}


