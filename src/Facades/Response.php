<?php 

namespace Zahzah\LaravelSupport\Facades;

use Illuminate\Support\Facades\Facade;
use Zahzah\LaravelSupport\Contracts\Response as ContractsResponse;

/**
 * @method static void exceptionRespond(Exceptions $exceptions)
 */
class Response extends Facade{

   protected static function getFacadeAccessor()
   {
      return ContractsResponse::class;
   }
}


