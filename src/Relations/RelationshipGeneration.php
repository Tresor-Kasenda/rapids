<?php

declare(strict_types=1);

namespace Rapids\Rapids\Relations;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Rapids\Rapids\Concerns\ModelFieldsGenerator;
use Rapids\Rapids\Infrastructure\Laravel\LaravelPromptService;

use function Illuminate\Filesystem\glob;
use function Illuminate\Foundation\Application\config;
use function Illuminate\Foundation\Application\database_path;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

final class RelationshipGeneration
{
    public function __construct(
        public string $modelName
    ) {
    }

    public function generateRelationMethods(array $relations): string
    {
        if (empty($relations)) {
            return '';
        }

        $methods = [];
        foreach ($relations as $relation) {
            $methodName = Str::camel($relation['model']);

            if ('morphTo' === $relation['type']) {
                $methods[] = $this->generateMorphToMethod($methodName);
                continue;
            }

            $methods = $this->relationGeneration($relation, $methodName, $methods);
        }

        return implode("\n\n    ", array_filter($methods));
    }

    private function generateMorphToMethod(string $methodName): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\MorphTo\n".
            "    {\n".
            "        return \$this->morphTo();\n".
            "    }";
    }

    public function relationGeneration(mixed $relationType, string $methodName, mixed $modelOrMethods): array|string
    {
        // Handle different parameter patterns
        if (is_array($relationType) && isset($relationType['type'])) {
            // Called from generateRelationMethods with relation array
            $type = $relationType['type'];
            $model = $relationType['model'];
            $methods = $modelOrMethods;

            $methods[] = $this->generateRelationMethod($type, $methodName, $model);
            return $methods;
        } else {
            // Called from addRelationToRelatedModel with relation type string
            $type = $relationType;
            $model = is_array($modelOrMethods) && ! empty($modelOrMethods) ? $modelOrMethods[0] : '';

            return $this->generateRelationMethod($type, $methodName, $model);
        }
    }

    private function generateRelationMethod(string $type, string $methodName, string $model): string
    {
        return match ($type) {
            'hasOne' => $this->generateHasOneMethod($methodName, $model),
            'belongsTo' => $this->generateBelongsToMethod($methodName, $model),
            'belongsToMany' => $this->generateBelongsToManyMethod($methodName, $model),
            'hasMany' => $this->generateHasManyMethod($methodName, $model),
            'hasOneThrough' => $this->generateHasOneThroughMethod($methodName, $model),
            'hasManyThrough' => $this->generateHasManyThroughMethod($methodName, $model),
            'morphOne' => $this->generateMorphOneMethod($methodName, $model),
            'morphMany' => $this->generateMorphManyMethod($methodName, $model),
            'morphToMany' => $this->generateMorphToManyMethod($methodName, $model),
            'morphedByMany' => $this->generateMorphedByManyMethod($methodName, $model),
            default => ''
        };
    }

    private function generateHasOneMethod(string $methodName, string $model): string
    {
        // The foreign key is defined on the related model's table (handled by belongsTo)
        // Default foreign key: Str::snake($this->modelName) . '_id'
        // Default local key: 'id'
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasOne\n".
            "    {\n".
            "        return \$this->hasOne({$model}::class); // Laravel defaults are usually sufficient\n".
            // "        // Example with explicit keys: return \$this->hasOne({$model}::class, 'foreign_key', 'local_key');\n".
            "    }";
    }

    private function generateBelongsToMethod(string $methodName, string $model): string
    {
        // Derive the foreign key from the *related* model name (convention)
        // e.g., if $model is 'User', foreign key is 'user_id'
        // The method name ($methodName) often matches the singular related model, but we derive FK from the $model class name for consistency.
        $foreignKey = Str::snake(Str::singular(class_basename($model))).'_id';

        // Default owner key: 'id'
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\BelongsTo\n".
            "    {\n".
            "        return \$this->belongsTo({$model}::class, '{$foreignKey}'); // Laravel defaults owner key to 'id'\n".
            // "        // Example with explicit keys: return \$this->belongsTo({$model}::class, 'foreign_key', 'owner_key');\n".
            "    }";
    }

    private function generateBelongsToManyMethod(string $methodName, string $model): string
    {
        // Generate the pivot table name in alphabetical order (Laravel convention)
        // Use class base names to avoid issues with namespaces if models are in different directories
        $model1Base = class_basename($this->modelName);
        $model2Base = class_basename($model);
        $table1 = Str::snake(Str::singular($model1Base));
        $table2 = Str::snake(Str::singular($model2Base));
        $pivotTableName = collect([$table1, $table2])->sort()->implode('_');

        // Define foreign keys based on model base names
        $foreignKey = Str::snake(Str::singular($model1Base)).'_id';
        $relatedKey = Str::snake(Str::singular($model2Base)).'_id';

        $withTimestamps = confirm(
            label: "Add timestamps to the pivot table?",
            default: false
        );

        // Create pivot table migration if it doesn't exist
        $this->createPivotTableMigration($pivotTableName, $foreignKey, $relatedKey, $this->modelName, $model, $withTimestamps);

        $code = "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\BelongsToMany\n".
            "    {\n".
            "        return \$this->belongsToMany(\n".
            "            {$model}::class,\n".
            "            '{$pivotTableName}',\n".
            "            '{$foreignKey}',\n".
            "            '{$relatedKey}'\n".
            "        )";
            
        if ($withTimestamps) {
            $code .= "\n            ->withTimestamps()";
        }
        
        $code .= ";\n    }";
        
        return $code;
    }

    private function createPivotTableMigration(
        string $pivotTable,
        string $foreignKey,
        string $relatedKey,
        string $model1,
        string $model2,
        bool $withTimestamps = false
    ): void {
        $migrationName = "create_{$pivotTable}_table";
        $migrationPath = database_path("migrations/".date('Y_m_d_His_').$migrationName.'.php');

        $existingMigrations = glob(database_path('migrations/*'.$migrationName.'.php'));
        if (!empty($existingMigrations)) {
            info("Pivot table migration already exists for {$pivotTable}");
            return;
        }

        $hasAdditionalFields = confirm(
            label: "Would you like to add additional fields to the {$pivotTable} pivot table?",
            default: false
        );

        $stub = File::get(config('rapids.stubs.migration.migration'));

        $fields = "\n";
        $fields .= "\$table->foreignId('{$foreignKey}')->constrained()->cascadeOnDelete();\n";
        $fields .= "\$table->foreignId('{$relatedKey}')->constrained()->cascadeOnDelete();\n";

        if ($hasAdditionalFields) {
            $promptService = new LaravelPromptService();
            $modelFieldsGenerator = new ModelFieldsGenerator($this->modelName, null, $promptService);
            $additionalFields = $modelFieldsGenerator->generate();

            foreach ($additionalFields as $field => $options) {
                if (!str_ends_with($field, '_id')) {  // Skip foreign keys as we already have them
                    if ('enum' === $options['type']) {
                        $values = array_map(fn ($value) => "'{$value}'", $options['values']);
                        $fields .= "\$table->enum('{$field}', [".implode(', ', $values)."])";
                        if (!empty($options['values'])) {
                            $fields .= "->default('{$options['values'][0]}')";
                        }
                    } else {
                        $fields .= "\$table->{$options['type']}('{$field}')";
                    }
                    if ($options['nullable']) {
                        $fields .= "->nullable()";
                    }
                    $fields .= ";\n";
                }
            }
        }

        if ($withTimestamps) {
            $fields .= "\$table->timestamps();\n";
        }

        $fields .= "\n";

        $content = str_replace(
            ['{{ table }}', '{{ fields }}'],
            [$pivotTable, $fields],
            $stub
        );

        File::put($migrationPath, $content);
        info("Created pivot table migration for {$model1} and {$model2}");
    }

    private function generateHasManyMethod(string $methodName, string $model): string
    {
        // The foreign key is defined on the related model's table (handled by belongsTo)
        // Default foreign key: Str::snake($this->modelName) . '_id'
        // Default local key: 'id'
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasMany\n".
            "    {\n".
            "        return \$this->hasMany({$model}::class); // Laravel defaults are usually sufficient\n".
            // "        // Example with explicit keys: return \$this->hasMany({$model}::class, 'foreign_key', 'local_key');\n".
            "    }";
    }

    private function generateHasOneThroughMethod(string $methodName, string $model): string
    {
        // Ask for the intermediate model name
        $intermediateModel = text(
            label: "Enter the intermediate model name for the HasOneThrough relationship",
            placeholder: 'e.g. Car for a Mechanic->Car->Owner relationship',
            required: true
        );

        // Optionally ask for foreign keys if they're non-standard
        $foreignKey = text(
            label: "Enter the foreign key on the intermediate model (or leave empty for default)",
            placeholder: "e.g. mechanic_id"
        );

        $secondForeignKey = text(
            label: "Enter the foreign key on the target model (or leave empty for default)",
            placeholder: "e.g. car_id"
        );

        $localKey = text(
            label: "Enter the local key on this model (or leave empty for default)",
            placeholder: "e.g. id"
        );

        $secondLocalKey = text(
            label: "Enter the local key on the intermediate model (or leave empty for default)",
            placeholder: "e.g. id"
        );

        // Build the relationship method with proper parameters
        $code = "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasOneThrough\n".
            "    {\n".
            "        return \$this->hasOneThrough(\n".
            "            {$model}::class,\n".
            "            {$intermediateModel}::class";

        // Add optional parameters if provided
        if ( ! empty($foreignKey) || ! empty($secondForeignKey) || ! empty($localKey) || ! empty($secondLocalKey)) {
            if ( ! empty($foreignKey)) {
                $code .= ",\n'{$foreignKey}'";
            } else {
                $code .= ",\nnull";
            }

            if ( ! empty($secondForeignKey)) {
                $code .= ",\n'{$secondForeignKey}'";
            } else {
                $code .= ",\nnull";
            }

            if ( ! empty($localKey)) {
                $code .= ",\n{$localKey}'";
            } else {
                $code .= ",\nnull";
            }

            if ( ! empty($secondLocalKey)) {
                $code .= ",\n'{$secondLocalKey}'";
            } else {
                $code .= ",\nnull";
            }
        }

        $code .= "\n);\n }";

        return $code;
    }

    private function generateHasManyThroughMethod(string $methodName, string $model): string
    {
        // Ask for the intermediate model name
        $intermediateModel = text(
            label: "Enter the intermediate model name for the HasManyThrough relationship",
            placeholder: 'e.g. Country for a Continent->Country->User relationship',
            required: true
        );

        // Optionally ask for foreign keys if they're non-standard
        $foreignKey = text(
            label: "Enter the foreign key on the intermediate model (or leave empty for default)",
            placeholder: "e.g. continent_id"
        );

        $secondForeignKey = text(
            label: "Enter the foreign key on the target model (or leave empty for default)",
            placeholder: "e.g. country_id"
        );

        $localKey = text(
            label: "Enter the local key on this model (or leave empty for default)",
            placeholder: "e.g. id"
        );

        $secondLocalKey = text(
            label: "Enter the local key on the intermediate model (or leave empty for default)",
            placeholder: "e.g. id"
        );

        // Build the relationship method with proper parameters
        $code = "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasManyThrough\n".
            "    {\n".
            "        return \$this->hasManyThrough(\n".
            "            {$model}::class,\n".
            "            {$intermediateModel}::class";

        // Add optional parameters if provided
        if (!empty($foreignKey) || !empty($secondForeignKey) || !empty($localKey) || !empty($secondLocalKey)) {
            if (!empty($foreignKey)) {
                $code .= ",\n            '{$foreignKey}'";
            } else {
                $code .= ",\n            null";
            }

            if (!empty($secondForeignKey)) {
                $code .= ",\n            '{$secondForeignKey}'";
            } else {
                $code .= ",\n            null";
            }

            if (!empty($localKey)) {
                $code .= ",\n            '{$localKey}'";
            } else {
                $code .= ",\n            null";
            }

            if (!empty($secondLocalKey)) {
                $code .= ",\n            '{$secondLocalKey}'";
            } else {
                $code .= ",\n            null";
            }
        }

        $code .= "\n        );\n    }";

        return $code;
    }

    private function generateMorphOneMethod(string $methodName, string $model): string
    {
        // Morph name derived from the *current* model name + 'able'
        $morphName = Str::snake(class_basename($this->modelName)).'able';
        
        // Ask for a custom morph name
        $customMorphName = text(
            label: "Enter the morph name for the relationship (or leave empty for default)",
            placeholder: $morphName,
            default: $morphName,
        );
        
        // Use custom name if provided
        $actualMorphName = !empty($customMorphName) ? $customMorphName : $morphName;
        
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\MorphOne\n".
            "    {\n".
            "        return \$this->morphOne({$model}::class, '{$actualMorphName}');\n".
            "    }";
    }

    private function generateMorphManyMethod(string $methodName, string $model): string
    {
        // Morph name derived from the *current* model name + 'able'
        $morphName = Str::snake(class_basename($this->modelName)).'able';
        
        // Ask for a custom morph name
        $customMorphName = text(
            label: "Enter the morph name for the relationship (or leave empty for default)",
            placeholder: $morphName,
            default: $morphName,
        );
        
        // Use custom name if provided
        $actualMorphName = !empty($customMorphName) ? $customMorphName : $morphName;
        
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\MorphMany\n".
            "    {\n".
            "        return \$this->morphMany({$model}::class, '{$actualMorphName}');\n".
            "    }";
    }

    private function generateMorphToManyMethod(string $methodName, string $model): string
    {
        // Default morph name derived from method name
        $morphName = Str::singular(Str::snake($methodName)).'able';
        
        // Ask for a custom morph name
        $customMorphName = text(
            label: "Enter the morph name for the relationship (or leave empty for default)",
            placeholder: $morphName,
            default: $morphName,
        );
        
        // Use custom name if provided
        $actualMorphName = !empty($customMorphName) ? $customMorphName : $morphName;
        
        // Ask if we should add timestamps
        $withTimestamps = confirm(
            label: "Add timestamps to pivot table?",
            default: false
        );
        
        // Ask if we should add a custom pivot table name
        $defaultTable = "{$actualMorphName}s"; // typical default for morphToMany
        $customTable = confirm(
            label: "Customize pivot table name?",
            default: false
        );
        
        $tableName = $defaultTable;
        if ($customTable) {
            $tableName = text(
                label: "Enter custom pivot table name",
                placeholder: $defaultTable,
                default: $defaultTable
            );
        }
        
        $code = "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\MorphToMany\n".
            "    {\n".
            "        return \$this->morphToMany({$model}::class, '{$actualMorphName}'";
            
        // Add optional table name if custom
        if ($customTable) {
            $code .= ", '{$tableName}'";
        }
        
        $code .= ")";
        
        // Append withTimestamps if requested
        if ($withTimestamps) {
            $code .= "\n            ->withTimestamps()";
        }
        
        // Close the method
        $code .= ";\n    }";
        
        return $code;
    }

    private function generateMorphedByManyMethod(string $methodName, string $model): string
    {
        // Default morph name derived from method name
        $morphName = Str::singular(Str::snake($methodName)).'able';
        
        // Ask for a custom morph name
        $customMorphName = text(
            label: "Enter the morph name for the relationship (or leave empty for default)",
            placeholder: $morphName,
            default: $morphName,
        );
        
        // Use custom name if provided
        $actualMorphName = !empty($customMorphName) ? $customMorphName : $morphName;
        
        // Ask if we should add timestamps
        $withTimestamps = confirm(
            label: "Add timestamps to pivot table?",
            default: false
        );
        
        // Ask if we should add a custom pivot table name
        $defaultTable = "{$actualMorphName}s"; // typical default
        $customTable = confirm(
            label: "Customize pivot table name?",
            default: false
        );
        
        $tableName = $defaultTable;
        if ($customTable) {
            $tableName = text(
                label: "Enter custom pivot table name",
                placeholder: $defaultTable,
                default: $defaultTable
            );
        }
        
        $code = "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\MorphedByMany\n".
            "    {\n".
            "        return \$this->morphedByMany({$model}::class, '{$actualMorphName}'";
            
        // Add optional table name if custom
        if ($customTable) {
            $code .= ", '{$tableName}'";
        }
        
        $code .= ")";
        
        // Append withTimestamps if requested
        if ($withTimestamps) {
            $code .= "\n            ->withTimestamps()";
        }
        
        // Close the method
        $code .= ";\n    }";
        
        return $code;
    }
}
