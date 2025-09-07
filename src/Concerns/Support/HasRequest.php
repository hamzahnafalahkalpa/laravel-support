<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Exception;
use Illuminate\Support\Facades\{
  Validator,
  Log,
  DB
};
use Hanafalah\LaravelSupport\Facades\LaravelSupport;
use Hanafalah\LaravelSupport\Supports\Data;
use Illuminate\Support\Facades\Request;
use ReflectionClass;

/**
 * @method static self validatingParam(array $case=[])
 * @method static self paramSetup()
 * @method static self setCase($cases)
 * @method static mixed callback($callback)
 * @method static Exception terminate($message='')
 * @method static mixed transaction($callback)
 */
trait HasRequest
{
  use RequestManipulation, HasArray, HasRequestData;

  protected $__exception = ["_token", 'button'];
  protected $__case      = [];
  protected $__validator;

  /**
   * method untuk memvalidasi request
   * @param Request $r
   * @param rules $valid
   * @param boolean $sendDetailError jika true seluruh error validasi akan ditamplikan di flash
   *
   * @return boolean
   */
  public function validatingParam(array $case = [])
  {
    if (count($case) > 0) $this->setCase($case);
    $validator = Validator::make(request()->all(), $this->__case);
    if ($validator->fails()) {
      $this->__validator = $validator;
      LaravelSupport::catch(new Exception($validator->errors()));
      return false;
    }
    return true;
  }

  /**
   * Merge the array from URL parameters into the request object.
   *
   * This function checks if the current request has a route associated with it.
   * If it does, it retrieves the parameters from the route and merges them into
   * the request object.
   *
   * @return self
   */
  protected function paramSetup(): self
  {
    //MERGIN ARRAY FROM URL PARAMS
    if (request()->route()) {
      $parameters = request()->route()->parameters();
      request()->merge($parameters);
    }
    return $this;
  }

  public function setCase($cases): self
  {
    $this->__case = $cases;
    return $this;
  }

  protected function callback($callback): mixed
  {
    return $callback();
  }

  public function terminate($message = ''): Exception
  {
    DB::rollBack();
    throw new \Exception($message);
  }

  public function transaction($callback): mixed
  {
    if (config('micro-tenant') !== null){
      if (env('DB_DRIVER') == 'mysql'){ 
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        DB::statement('SET GLOBAL FOREIGN_KEY_CHECKS = 0;');
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        DB::statement('SET GLOBAL TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        DB::statement('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
      }
    }

    $user_connections = [];
    try {
      DB::listen(function ($query) use (&$user_connections) {
        $connection_name = $query->connectionName;
        if (!in_array($connection_name, $user_connections)) {
          $user_connections[] = $connection_name;
          DB::connection($connection_name)->beginTransaction();
        }
      });

      $value = $callback();
      foreach ($user_connections as $connection_name) {
        DB::connection($connection_name)->commit();
      }
      $result = true;
    } catch (\Throwable $e) {
      foreach ($user_connections as $connection_name) {
        try {
            DB::connection($connection_name)->rollBack();
        } catch (\Throwable $ex) {
            // kalau sudah aborted, rollback bisa gagal → biarin aja
        }

        // reset connection supaya status "aborted" hilang
        DB::purge($connection_name);
        DB::reconnect($connection_name);
      }

      LaravelSupport::catch($e);
      $result = false;
      if (Request::wantsJson()) {
        throw $e;
      }
    }
    return (isset($value)) ? $value : $result;
  }
}
