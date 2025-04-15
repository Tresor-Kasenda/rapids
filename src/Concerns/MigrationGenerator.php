<?php

declare(strict_types=1);

namespace Rapids\Rapids\Concerns;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

final class MigrationGenerator
{
    /**
     * Default constraint types for foreign keys
     */
    private const CONSTRAINT_TYPES = [
        'cascade' => 'CASCADE (delete related records)',
        'restrict' => 'RESTRICT (prevent deletion)',
        'nullify' => 'SET NULL (set null on deletion)',
    ];

    /**
     * @param string|null $modelName The name of the model
     * @param bool $interactive Whether to use interactive prompts
     */
    public function __construct(
        public ?string $modelName,
        private bool   $interactive = true
    ) {
    }

    /**
     * Generate a migration file for the model
     *
     * @param array $fields The fields for the migration
     * @return void
     * @throws RuntimeException If migration generation fails
     */
    public function generateMigration(array $fields, bool $softDeletes = false): void
    {
        try {
            $tableName = Str::snake(Str::pluralStudly($this->modelName));
            $migrationName = "create_{$tableName}_table";
            $migrationFile = $this->getMigrationFilePath($migrationName);

            $stub = File::get(config('rapids.stubs.migration.migration'));
            $tableFields = $this->generateMigrationFields($fields);

            if ($softDeletes) {
                $tableFields .= "\n\$table->softDeletes();";
            }

            $migrationContent = $this->replacePlaceholders($stub, $tableName, $tableFields);

            File::put($migrationFile, $migrationContent);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to generate migration: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the full path for a migration file
     *
     * @param string $migrationName
     * @return string
     */
    private function getMigrationFilePath(string $migrationName): string
    {
        $timestamp = date('Y_m_d_His_');
        return database_path("migrations/{$timestamp}{$migrationName}.php");
    }

    /**
     * Generate migration fields based on field configuration
     *
     * @param array $fields
     * @return string
     */
    public function generateMigrationFields(array $fields): string
    {
        $tableFields = [];

        foreach ($fields as $field => $options) {
            if (str_ends_with($field, '_id')) {
                $tableFields[] = $this->generateForeignKeyField($field, $options);
            } else {
                $tableFields[] = $this->generateRegularField($field, $options);
            }
        }

        return implode("\n", $tableFields);
    }

    /**
     * Generate a foreign key field definition
     *
     * @param string $field
     * @param array $options
     * @return string
     */
    private function generateForeignKeyField(string $field, array $options): string
    {
        $suggestedTable = Str::plural(Str::beforeLast($field, '_id'));
        $relatedTable = $suggestedTable;
        $constraintType = 'cascade';

        if ($this->interactive) {
            $relatedTable = text(
                label: "Enter related table name for {$field}",
                placeholder: $suggestedTable,
                default: $suggestedTable
            );

            $constraintType = search(
                label: "Select constraint type for {$field}",
                options: fn () => self::CONSTRAINT_TYPES
            );
        }

        $constraintMethod = match ($constraintType) {
            'cascade' => '->cascadeOnDelete()',
            'restrict' => '->restrictOnDelete()',
            'nullify' => '->nullOnDelete()',
            default => '->cascadeOnDelete()',
        };

        $nullable = $options['nullable'] ?? false;
        $nullableMethod = $nullable ? '->nullable()' : '';

        return "\$table->foreignId('{$field}')"
            ."->constrained('{$relatedTable}')"
            .$constraintMethod
            .$nullableMethod
            .";";
    }

    /**
     * Generate a regular field definition
     *
     * @param string $field
     * @param array $options
     * @return string
     */
    private function generateRegularField(string $field, array $options): string
    {
        $type = $options['type'] ?? 'string';
        $nullable = $options['nullable'] ?? false;

        if ('enum' === $type) {
            return $this->generateEnumField($field, $options);
        }

        $fieldDefinition = "\$table->{$type}('{$field}')";

        if ($nullable) {
            $fieldDefinition .= '->nullable()';
        }

        return $fieldDefinition.';';
    }

    /**
     * Generate an enum field definition
     *
     * @param string $field
     * @param array $options
     * @return string
     */
    private function generateEnumField(string $field, array $options): string
    {
        $values = array_map(fn ($value) => "'{$value}'", $options['values'] ?? []);
        $fieldDefinition = "\$table->enum('{$field}', [".implode(', ', $values)."])";

        if ( ! empty($options['values'])) {
            $defaultValue = $options['values'][0];
            $fieldDefinition .= "->default('{$defaultValue}')";
        }

        if ($options['nullable'] ?? false) {
            $fieldDefinition .= '->nullable()';
        }

        return $fieldDefinition.';';
    }

    /**
     * Replace placeholders in the migration stub
     *
     * @param string $stub
     * @param string $tableName
     * @param string $tableFields
     * @return string
     */
    private function replacePlaceholders(string $stub, string $tableName, string $tableFields): string
    {
        return str_replace(
            ['{{ table }}', '{{ fields }}'],
            [$tableName, $tableFields],
            $stub
        );
    }
}
