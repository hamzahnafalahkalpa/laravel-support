<?php

namespace Hanafalah\LaravelSupport\Concerns;

use Illuminate\Support\Facades\Schema;

trait NowYouSeeMe
{
    protected $__table_name;

    public function isTableExists()
    {
        return $this->schema(function ($schema, $table_name) {
            return $schema->hasTable($table_name);
        });
    }

    public function isColumnExists($column_name)
    {
        return $this->schema(function ($schema, $table_name) use ($column_name) {
            return $schema->hasColumn($table_name, $column_name);
        });
    }

    private function schema(callable $callback)
    {
        $this->__table_name = $this->__table->getTable();
        $schema = Schema::connection($this->__table->getConnectionName());
        return $callback($schema, explode('.', $this->__table_name)[1] ?? $this->__table_name);
    }
}
