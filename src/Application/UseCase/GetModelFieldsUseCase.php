<?php

declare(strict_types=1);

namespace Rapids\Rapids\Application\UseCase;

use Rapids\Rapids\Domain\Port\ModelRepositoryInterface;
use Rapids\Rapids\Domain\Port\SchemaRepositoryInterface;
use RuntimeException;

final class GetModelFieldsUseCase
{
    private array $systemColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function __construct(
        private readonly ModelRepositoryInterface  $modelRepository,
        private readonly SchemaRepositoryInterface $schemaRepository
    ) {
    }

    public function execute(string $modelName): array
    {
        if ( ! $this->modelRepository->exists($modelName)) {
            throw new RuntimeException("Model {$modelName} does not exist.");
        }

        $model = $this->modelRepository->getInstance($modelName);
        $tableName = $this->schemaRepository->getTableName($model);
        $columns = $this->schemaRepository->getColumnListing($tableName);

        $fields = [];
        $relationFields = [];

        foreach ($columns as $column) {
            if (in_array($column, $this->systemColumns)) {
                continue;
            }

            $type = $this->schemaRepository->getColumnType($tableName, $column);
            $mappedType = $this->mapDatabaseType($type, $column);

            $fields[$column] = $mappedType;

            if (str_ends_with($column, '_id')) {
                $relationFields[$column] = $column;
            }
        }

        return [
            'fields' => $fields,
            'relations' => $relationFields
        ];
    }

    private function mapDatabaseType(string $type, string $column): string
    {
        return match ($type) {
            'string', 'text', 'varchar', 'longtext' => 'string',
            'integer', 'bigint', 'smallint' => 'integer',
            'decimal', 'float', 'double' => 'float',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'json', 'array' => 'json',
            default => 'string',
        };
    }
}
