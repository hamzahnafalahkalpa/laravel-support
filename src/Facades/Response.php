<?php

namespace Hanafalah\LaravelSupport\Facades;

use Illuminate\Support\Facades\Facade;
use Hanafalah\LaravelSupport\Contracts\Response as ContractsResponse;

/**
 * @method static void exceptionRespond(Exceptions $exceptions)
 */
class Response extends Facade
{

   protected static function getFacadeAccessor()
   {
      return ContractsResponse::class;
   }
}
