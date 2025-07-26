<?php

namespace Hanafalah\LaravelSupport\Commands\Concerns;

use Illuminate\Support\Str;

trait MigrationHelper
{

    /** @var string */
    public string $__prefix, $__stub, $__suffix, $__name, $__feature, $__entity_name;
    /** @var array  */
    protected array $__prefixes = ['add_feature_to_', 'add_column_to_', 'update_column_in_', 'create_'];
    protected array $__suffixes = ['_table', '_function', '_event', '_procedure', '_trigger'];

    protected function findPrefix(): self
    {
        foreach ($this->__prefixes as $prefix) {
            if (Str::startsWith($this->__name, $prefix)) {
                $this->__prefix = $prefix;
                if ($this->__prefix == 'create_') $this->findSuffix();
                break;
            }
        }
        return $this;
    }

    protected function hasPrefix(): bool
    {
        return isset($this->__prefix);
    }

    protected function findSuffix(): self
    {
        foreach ($this->__suffixes as $suffix) {
            if (Str::endsWith($this->__name, $suffix)) {
                $this->__suffix = $suffix;
                break;
            }
        }
        return $this;
    }

    protected function hasSuffix(): bool
    {
        return isset($this->__suffix);
    }

    protected function findAppropriateStub()
    {
        if (isset($this->__prefix)) {
            switch ($this->__prefix) {
                case 'add_column_to':
                case 'update_column_in':
                    $this->__stub = 'migration-update';

                    break;
                case 'create_':
                    $this->__stub = $this->chooseCreateStub();
                    break;
            }
        } else {
            $this->__stub = 'migration';
        }
    }

    protected function getFileName($name = null)
    {
        $name     = ($this->hasPrefix()) ? $this->__prefix . $this->__entity_name . $this->__suffix : $name;
        $filename = $this->laravel['migration.creator']->create($name, '');
        return $filename;
    }

    protected function chooseCreateStub(): string
    {
        switch ($this->__suffix) {
            case '_table':
                $stub = "migration.create";
                break;
            default:
                $stub = 'migration' . \str_replace('_', '.', $this->__suffix);
                break;
        }
        $this->__entity_name = $this->normalize($this->__name);
        return $stub;
    }

    protected function normalize(string $str, string $prefix = null, string $suffix = null): string
    {
        return strtolower(str_replace('_', '.', str_replace([$prefix ?? $this->__prefix, $suffix ?? $this->__suffix], '', $str)));
    }
}
