<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

trait HasLocalDir
{
    /**
     * Retrieves the path of the local directory.
     *
     * @return string
     */
    protected function dir(): string
    {
        return __DIR__ . '/../../';
    }
}
