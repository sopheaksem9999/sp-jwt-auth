<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers Schema::hasColumn() answers (an information_schema query) for the
 * life of the container, keyed by connection, table and column.
 */
final class SchemaColumnCache
{
    /** @var array<string, bool> */
    private array $columns = [];

    public function has(Model $model, string $column): bool
    {
        $connection = $model->getConnectionName() ?? config('database.default');
        $key = $connection . '.' . $model->getTable() . '.' . $column;

        return $this->columns[$key] ??= Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $column);
    }

    public function flush(): void
    {
        $this->columns = [];
    }
}
