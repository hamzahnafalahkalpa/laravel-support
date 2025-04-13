<?php

namespace Hanafalah\LaravelSupport\Contracts\Supports;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface DataManagement
{
    //CLASS MANIPULATION
    public function useSchema(string $className): DataManagement;
    public function getClass(): mixed;
    public function flushTagsFrom(string $category, ?string $tags = null, ?string $suffix = null);
    public function setParamLogic(string $logic): self;
    public function getParamLogic(): string;
    public function schemaContract(string $contract);
    public function autolist(?string $response = 'list',?callable $callback = null);

    //REQUEST MANIPULATION
    public function moveTo(string $field, array $new_fields): self;

    //ORM MANIPULATION
    public function conditionals(mixed $conditionals): self;
}
