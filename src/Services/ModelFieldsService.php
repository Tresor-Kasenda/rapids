<?php

namespace Rapids\Rapids\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ModelFieldsService
{
    public function __construct(
        public string $modelName,
        public array  $selectedFields,
        public array  $relationFields
    )
    {
    }

    public function getModelFields(): array
    {
        $modelPath = app_path("Models/{$this->modelName}.php");

        // Vérifier si le fichier du modèle existe physiquement
        if (!File::exists($modelPath)) {
            throw new RuntimeException("Model file for {$this->modelName} does not exist at {$modelPath}");
        }

        // Forcer le rechargement du fichier
        require_once $modelPath;

        $modelClass = "App\\Models\\{$this->modelName}";

        if (!class_exists($modelClass)) {
            throw new RuntimeException("Model class {$modelClass} could not be loaded.");
        }

        $instance = new $modelClass();
        $tableName = $instance->getTable();

        // Get all table columns
        $columns = Schema::getColumnListing($tableName);
        $fields = [];

        foreach ($columns as $column) {
            // Skip internal columns
            if (in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $type = Schema::getColumnType($tableName, $column);

            // Map database types to our types
            $fields[$column] = match ($type) {
                'string', 'text', 'varchar', 'longtext' => 'string',
                'integer', 'bigint', 'smallint' => str_ends_with($column, '_id') ? 'integer' : 'integer',
                'decimal', 'float', 'double' => 'float',
                'boolean' => 'boolean',
                'date' => 'date',
                'datetime', 'timestamp' => 'datetime',
                'json', 'array' => 'json',
                default => 'string',
            };

            // Track relation fields
            if (str_ends_with($column, '_id')) {
                $this->relationFields[$column] = $column;
            }
        }

        $this->selectedFields = $fields;
        return $fields;
    }
}
