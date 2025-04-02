<?php

namespace Rapids\Rapids\Infrastructure\Repository;

use Illuminate\Support\Facades\Schema;
use Rapids\Rapids\Domain\Port\SchemaRepositoryInterface;

class LaravelSchemaRepository implements SchemaRepositoryInterface
{
    public function getTableName(object $model): string
    {
        return $model->getTable();
    }

    public function getColumnListing(string $tableName): array
    {
        return Schema::getColumnListing($tableName);
    }

    public function getColumnType(string $tableName, string $column): string
    {
        return Schema::getColumnType($tableName, $column);
    }
}
