<?php

namespace Rapids\Rapids\Relations;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Rapids\Rapids\Concerns\ModelFieldsGenerator;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

final class RelationshipGeneration
{
    public function __construct(
        public string $modelName
    )
    {
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

    protected function generateMorphToMethod(string $methodName): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\MorphTo\n" .
            "    {\n" .
            "        return \$this->morphTo();\n" .
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
            $model = is_array($modelOrMethods) && !empty($modelOrMethods) ? $modelOrMethods[0] : '';

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

    protected function generateHasOneMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasOne\n" .
            "    {\n" .
            "        return \$this->hasOne({$model}::class);\n" .
            "    }";
    }

    protected function generateBelongsToMethod(string $methodName, string $model): string
    {
        // Derive the foreign key from the method name
        $foreignKey = Str::snake($methodName) . '_id';

        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\BelongsTo\n" .
            "    {\n" .
            "        return \$this->belongsTo({$model}::class, '{$foreignKey}');\n" .
            "    }";
    }

    protected function generateBelongsToManyMethod(string $methodName, string $model): string
    {
        // Generate the pivot table name in alphabetical order (Laravel convention)
        $table1 = Str::snake(Str::singular($this->modelName));
        $table2 = Str::snake(Str::singular($model));
        $pivotTableName = collect([$table1, $table2])->sort()->implode('_');

        // Define foreign keys
        $foreignKey = Str::snake($this->modelName) . '_id';
        $relatedKey = Str::snake($model) . '_id';

        // Create pivot table migration if it doesn't exist
        $this->createPivotTableMigration($pivotTableName, $foreignKey, $relatedKey, $this->modelName, $model);

        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\BelongsToMany\n" .
            "    {\n" .
            "        return \$this->belongsToMany(\n" .
            "            {$model}::class,\n" .
            "            '{$pivotTableName}',\n" .
            "            '{$foreignKey}',\n" .
            "            '{$relatedKey}'\n" .
            "        );\n" .
            "    }";
    }

    protected function createPivotTableMigration(
        string $pivotTable,
        string $foreignKey,
        string $relatedKey,
        string $model1,
        string $model2
    ): void
    {
        $migrationName = "create_{$pivotTable}_table";
        $migrationPath = database_path("migrations/" . date('Y_m_d_His_') . $migrationName . '.php');

        $existingMigrations = glob(database_path('migrations/*' . $migrationName . '.php'));
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
            $additionalFields = ModelFieldsGenerator::generateModelFields($this->modelName);
            foreach ($additionalFields as $field => $options) {
                if (!str_ends_with($field, '_id')) {  // Skip foreign keys as we already have them
                    if ('enum' === $options['type']) {
                        $values = array_map(fn($value) => "'{$value}'", $options['values']);
                        $fields .= "\$table->enum('{$field}', [" . implode(', ', $values) . "])";
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

        $fields .= "\n";

        $content = str_replace(
            ['{{ table }}', '{{ fields }}'],
            [$pivotTable, $fields],
            $stub
        );

        File::put($migrationPath, $content);
        info("Created pivot table migration for {$model1} and {$model2}");
    }

    protected function generateHasManyMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasMany\n" .
            "    {\n" .
            "        return \$this->hasMany({$model}::class);\n" .
            "    }";
    }

    protected function generateHasOneThroughMethod(string $methodName, string $model): string
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
        $code = "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasOneThrough\n" .
            "    {\n" .
            "        return \$this->hasOneThrough(\n" .
            "            {$model}::class,\n" .
            "            {$intermediateModel}::class";

        // Add optional parameters if provided
        if (!empty($foreignKey) || !empty($secondForeignKey) || !empty($localKey) || !empty($secondLocalKey)) {
            if (!empty($foreignKey)) {
                $code .= ",\n'{$foreignKey}'";
            } else {
                $code .= ",\nnull";
            }

            if (!empty($secondForeignKey)) {
                $code .= ",\n'{$secondForeignKey}'";
            } else {
                $code .= ",\nnull";
            }

            if (!empty($localKey)) {
                $code .= ",\n{$localKey}'";
            } else {
                $code .= ",\nnull";
            }

            if (!empty($secondLocalKey)) {
                $code .= ",\n'{$secondLocalKey}'";
            } else {
                $code .= ",\nnull";
            }
        }

        $code .= "\n);\n }";

        return $code;
    }

    protected function generateHasManyThroughMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasManyThrough\n" .
            "    {\n" .
            "        return \$this->hasManyThrough({$model}::class, Through::class);\n" .
            "    }";
    }

    protected function generateMorphOneMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\MorphOne\n" .
            "    {\n" .
            "        return \$this->morphOne({$model}::class, '" . Str::snake($this->modelName) . "able');\n" .
            "    }";
    }

    protected function generateMorphManyMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\MorphMany\n" .
            "    {\n" .
            "        return \$this->morphMany({$model}::class, '" . Str::snake($this->modelName) . "able');\n" .
            "    }";
    }

    protected function generateMorphToManyMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\MorphToMany\n" .
            "    {\n" .
            "        return \$this->morphToMany({$model}::class, '" . Str::snake($this->modelName) . "able');\n" .
            "    }";
    }

    protected function generateMorphedByManyMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\MorphedByMany\n" .
            "    {\n" .
            "        return \$this->morphedByMany({$model}::class, '" . Str::singular(Str::snake($methodName)) . "able');\n" .
            "    }";
    }
}
