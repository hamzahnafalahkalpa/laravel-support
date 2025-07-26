<?php

namespace Hanafalah\LaravelSupport\Facades;

use Illuminate\Support\Facades\Facade;
use Hanafalah\LaravelSupport\Contracts\LaravelSupport as ContractsLaravelSupport;

/**
 * @see \Hanafalah\LaravelSupport\LaravelSupport
 * @method static void exceptionRespond(Exceptions $exceptions)
 * @method static void callRoutes(string $path)
 * @method static void eventPipelines(object $class)
 */
class LaravelSupport extends Facade
{

   protected static function getFacadeAccessor()
   {
      return ContractsLaravelSupport::class;
   }
}
