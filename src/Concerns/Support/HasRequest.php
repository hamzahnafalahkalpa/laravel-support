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

  /**
   * Optimized transaction handler with proper isolation levels and deadlock prevention
   *
   * @param callable $callback
   * @param int $maxRetries Maximum number of retries on deadlock (default: 3)
   * @return mixed
   * @throws \Throwable
   */
  public function transaction(callable $callback, int $maxRetries = 3): mixed
  {
      $attempts = 0;

      while ($attempts < $maxRetries) {
          try {
              return $this->executeTransaction($callback);
          } catch (\Throwable $e) {
              $attempts++;

              // Check if it's a deadlock or serialization failure
              $isDeadlock = $this->isDeadlockException($e);
              $isSerializationFailure = $this->isSerializationException($e);

              if (($isDeadlock || $isSerializationFailure) && $attempts < $maxRetries) {
                  // Exponential backoff: wait before retry
                  $waitTime = min(100000 * pow(2, $attempts - 1), 1000000); // Max 1 second
                  usleep($waitTime);

                  // Log retry attempt
                  \Log::warning("Transaction deadlock detected, retrying ({$attempts}/{$maxRetries})", [
                      'error' => $e->getMessage(),
                      'code' => $e->getCode()
                  ]);

                  continue;
              }

              // If not a deadlock or max retries reached, rethrow
              throw $e;
          }
      }

      throw new \Exception("Transaction failed after {$maxRetries} attempts");
  }

  /**
   * Execute the actual transaction
   *
   * @param callable $callback
   * @return mixed
   * @throws \Throwable
   */
  protected function executeTransaction(callable $callback): mixed
  {
      // Track active connections
      $activeConnections = [];
      $listenerActive = true;

      // Set proper isolation level for PostgreSQL (READ COMMITTED is default and safest)
      // For MySQL, we keep default REPEATABLE READ
      if (env('DB_DRIVER') === 'pgsql') {
          DB::statement('SET LOCAL lock_timeout = \'10s\'');
          DB::statement('SET LOCAL statement_timeout = \'60s\'');
      }

      // Listen for queries to detect which connections need transactions
      $listenerId = DB::listen(function ($query) use (&$activeConnections, &$listenerActive) {
          if (!$listenerActive) {
              return;
          }

          $connection = $query->connectionName;
          $sql = ltrim(strtolower($query->sql));

          // Detect WRITE queries only (INSERT, UPDATE, DELETE, etc.)
          $isWrite = preg_match(
              '/^(insert|update|delete|merge|truncate|copy|create|alter|drop)\b/',
              $sql
          );

          // Start transaction only once per connection when first write is detected
          if ($isWrite && !isset($activeConnections[$connection])) {
              try {
                  $conn = DB::connection($connection);

                  // Start transaction
                  $conn->beginTransaction();

                  // Set isolation level and timeouts for this connection
                  if ($conn->getDriverName() === 'pgsql') {
                      $conn->statement('SET LOCAL lock_timeout = \'10s\'');
                      $conn->statement('SET LOCAL statement_timeout = \'60s\'');
                      $conn->statement('SET LOCAL idle_in_transaction_session_timeout = \'300s\'');
                  }

                  $activeConnections[$connection] = true;

                  \Log::debug("Transaction started on connection: {$connection}");
              } catch (\Throwable $e) {
                  \Log::error("Failed to start transaction on {$connection}: " . $e->getMessage());
                  throw $e;
              }
          }
      });

      try {
          // Execute the callback
          $result = $callback();

          // Stop listener before committing
          $listenerActive = false;
          DB::flushQueryLog(); // Clear query log

          // Commit all active connections in reverse order (LIFO)
          $connectionNames = array_reverse(array_keys($activeConnections));

          foreach ($connectionNames as $connection) {
              try {
                  $conn = DB::connection($connection);

                  if ($conn->transactionLevel() > 0) {
                      $conn->commit();
                      \Log::debug("Transaction committed on connection: {$connection}");
                  }
              } catch (\Throwable $commitError) {
                  \Log::error("Failed to commit transaction on {$connection}: " . $commitError->getMessage());
                  throw $commitError;
              }
          }

          return $result;

      } catch (\Throwable $e) {
          $listenerActive = false;

          // Rollback all active connections
          foreach (array_keys($activeConnections) as $connection) {
              try {
                  $conn = DB::connection($connection);

                  if ($conn->transactionLevel() > 0) {
                      $conn->rollBack();
                      \Log::debug("Transaction rolled back on connection: {$connection}");
                  }
              } catch (\Throwable $rollbackError) {
                  // Log but don't throw - we're already handling an exception
                  \Log::error("Failed to rollback transaction on {$connection}: " . $rollbackError->getMessage());
              }

              // For PostgreSQL, purge connection to clear any "aborted transaction" state
              if (DB::connection($connection)->getDriverName() === 'pgsql') {
                  try {
                      DB::purge($connection);
                      \Log::debug("Connection purged: {$connection}");
                  } catch (\Throwable $purgeError) {
                      \Log::error("Failed to purge connection {$connection}: " . $purgeError->getMessage());
                  }
              }
          }

          // Log the error
          LaravelSupport::catch($e);

          // Rethrow for retry logic or API response
          throw $e;
      }
  }

  /**
   * Check if exception is a deadlock
   *
   * @param \Throwable $e
   * @return bool
   */
  protected function isDeadlockException(\Throwable $e): bool
  {
      $message = $e->getMessage();
      $code = $e->getCode();

      // PostgreSQL deadlock codes
      if (str_contains($message, 'deadlock detected') || $code === '40P01') {
          return true;
      }

      // MySQL deadlock codes
      if ($code === 1213 || str_contains($message, 'Deadlock found')) {
          return true;
      }

      return false;
  }

  /**
   * Check if exception is a serialization failure
   *
   * @param \Throwable $e
   * @return bool
   */
  protected function isSerializationException(\Throwable $e): bool
  {
      $message = $e->getMessage();
      $code = $e->getCode();

      // PostgreSQL serialization failure
      if (str_contains($message, 'could not serialize access') || $code === '40001') {
          return true;
      }

      return false;
  }
}
