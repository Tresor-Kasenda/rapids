<?php

declare(strict_types=1);

namespace Rapids\Rapids\Concerns;

use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

final class ModelFields
{
    public static function generateModelFields(string $modelName): array
    {
        $fields = [];
        $continue = true;
        $existingFields = [];

        if (class_exists("App\\Models\\{$modelName}")) {
            $model = "App\\Models\\{$modelName}";
            $instance = new $model();
            $existingFields = Schema::getColumnListing($instance->getTable());
        }

        while ($continue) {
            $fieldName = text(
                label: 'Enter field name (or press enter to finish)',
                placeholder: 'e.g. name, email, phone',
            );

            if (empty($fieldName)) {
                break;
            }

            // Check if field already exists in model
            if (in_array($fieldName, $existingFields)) {
                info("Field '{$fieldName}' already exists in the model.");
                continue;
            }

            $fieldType = search(
                label: 'Select field type',
                options: fn () => [
                    'string' => 'String',
                    'text' => 'Text',
                    'integer' => 'Integer',
                    'bigInteger' => 'Big Integer',
                    'float' => 'Float',
                    'decimal' => 'Decimal',
                    'boolean' => 'Boolean',
                    'date' => 'Date',
                    'datetime' => 'DateTime',
                    'timestamp' => 'Timestamp',
                    'json' => 'JSON',
                    'enum' => 'Enum',
                    'uuid' => 'UUID',
                ],
                placeholder: 'Select field type'
            );

            $nullable = confirm(
                label: "Is this field nullable?",
                default: false
            );

            $fields[$fieldName] = [
                'type' => $fieldType,
                'nullable' => $nullable
            ];

            if ('enum' === $fieldType) {
                $values = text(
                    label: 'Enter enum values (comma-separated)',
                    placeholder: 'e.g. draft,published,archived'
                );
                $fields[$fieldName]['values'] = array_map('trim', explode(',', $values));
            }
        }

        return $fields;
    }
}
