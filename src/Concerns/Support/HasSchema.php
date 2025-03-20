<?php

namespace Hanafalah\MicroTenant\Concerns\Schema;

trait HasSchema
{
    protected $__schema_model;

    protected function getSchema(): self
    {
        $this->__schema_model = app($this->getModel(true, 'Schema'));
        return $this;
    }
}
