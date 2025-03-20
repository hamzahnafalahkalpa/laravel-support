<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Exception;
use Illuminate\Support\Facades\Log;

trait ErrorHandling
{
    public static ?Exception $exception;

    public function catch(Exception $exception): self
    {
        static::$exception = $exception;
        $this->code      = $exception->getCode();
        Log::error($this->messages  = $exception->getMessage());
        return $this;
    }

    public function getException(): ?Exception
    {
        return static::$exception ?? null;
    }

    public function fails(): bool
    {
        return static::$exception instanceof Exception;
    }
}
