<?php

declare(strict_types=1);

namespace Rapids\Rapids\Domain\Port;

interface SchemaRepositoryInterface
{
    public function getTableName(object $model): string;

    public function getColumnListing(string $tableName): array;

    public function getColumnType(string $tableName, string $column): string;
}
