<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Exception;
use Illuminate\Support\Facades\{
  Validator, DB
};
use Hanafalah\LaravelSupport\Facades\LaravelSupport;
use Illuminate\Support\Facades\Request;

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

  public function transaction(callable $callback): mixed
  {
      // khusus MySQL (kalau dipakai)
      if (config('micro-tenant') !== null && env('DB_DRIVER') === 'mysql') {
          DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
          DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
      }

      $user_connections = [];
      $listenerActive   = true;

      DB::listen(function ($query) use (&$user_connections, &$listenerActive) {
          if (! $listenerActive) {
              return;
          }

          $connection = $query->connectionName;
          $sql = ltrim(strtolower($query->sql));

          // DETEKSI WRITE SAJA
          $isWrite = preg_match(
              '/^(insert|update|delete|merge|truncate|copy)\b/',
              $sql
          );

          if ($isWrite && ! in_array($connection, $user_connections, true)) {
              $user_connections[] = $connection;
              DB::connection($connection)->beginTransaction();
          }
      });

      try {
          $result = $callback();

          // STOP listener sebelum commit
          $listenerActive = false;

          foreach ($user_connections as $connection) {
              DB::connection($connection)->commit();
          }

          return $result;
      } catch (\Throwable $e) {
          $listenerActive = false;

          foreach ($user_connections as $connection) {
              try {
                  DB::connection($connection)->rollBack();
              } catch (\Throwable) {
                  // ignore
              }

              // penting untuk Postgres (aborted transaction state)
              DB::purge($connection);
          }

          LaravelSupport::catch($e);

          if (Request::wantsJson()) {
              throw $e;
          }

          return false;
      }
  }
}
