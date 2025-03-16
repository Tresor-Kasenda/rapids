<?php

namespace Rapids\Rapids\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

class RapidsModels extends Command
{
    protected $signature = 'rapids:model {name?}';

    protected $description = '
        Create a new model with fields, relations, migration, factory, and seeder in one go.
    ';

    protected string $modelName;

    protected array $relationFields = [];

    protected array $selectedFields = [];

    public function handle(): void
    {
        // Get all PHP files in Models directory
        $modelPath = app_path('Models');
        $modelFiles = array_map(
            fn($file) => pathinfo($file, PATHINFO_FILENAME),
            glob($modelPath . '/*.php')
        );

        // Filter existing models
        $availableModels = array_filter($modelFiles, function ($model) {
            return class_exists("App\\Models\\{$model}");
        });

        $modelName = $this->argument('name') ?? text(
            label: 'Enter model name (without "App\\Models\\")',
            placeholder: 'e.g. User, Post, Product',
            required: true,
            validate: fn(string $value) => match (true) {
                strlen($value) < 2 => 'The model name must be at least 2 characters.',
                !preg_match('/^[A-Za-z]+$/', $value) => 'The model name must contain only letters.',
                default => null
            }
        );

        if (in_array($modelName, $availableModels)) {
            info("Model {$modelName} already exists.");

            $choice = search(
                label: 'What would you like to do?',
                options: fn() => [
                    'new' => 'Enter a different model name',
                    'migration' => 'Add new migration for existing model',
                    'cancel' => 'Cancel operation'
                ]
            );

            match ($choice) {
                'new' => $this->call('rapids:model'),
                'migration' => $this->handleExistingModel($modelName),
                'cancel' => info('Operation cancelled.')
            };

            return;
        }

        $this->modelName = ucfirst($modelName);

        $this->handleModelCreation();
        info('Running migrations...');
        $this->call('migrate');
        $this->generateFactory();
        $this->generateSeeder();
        info('Model created successfully.');
    }

    protected function handleExistingModel(bool|array|string $modelName): void
    {
        $this->modelName = $modelName;
        info("Adding new migration for {$modelName}");

        // Get new fields for the migration
        $fields = $this->getModelsFields();

        // Handle relations for fields ending with _id
        foreach ($fields as $field => $options) {
            if (str_ends_with($field, '_id')) {
                $relatedModelName = text(
                    label: "Enter related model name for {$field}",
                    placeholder: 'e.g. User for user_id',
                    required: true
                );

                // Add belongsTo relation to current model
                $this->addRelationToModel(
                    $this->modelName,
                    $relatedModelName,
                    'belongsTo',
                );

                // Add hasMany relation to related model
                $this->addRelationToModel(
                    $relatedModelName,
                    $this->modelName,
                    'hasMany',
                );
            }
        }

        // Generate migration
        $migrationName = 'add_fields_to_' . Str::snake(Str::pluralStudly($modelName)) . '_table';
        $migrationFile = database_path("migrations/" . date('Y_m_d_His_') . $migrationName . '.php');

        $stub = File::get(config('rapids.stubs.migration.alter'));
        $tableFields = $this->generateMigrationFields($fields);

        $migrationContent = str_replace(
            ['{{ table }}', '{{ fields }}'],
            [Str::snake(Str::pluralStudly($modelName)), $tableFields],
            $stub
        );

        File::put($migrationFile, $migrationContent);
        info('Migration created successfully.');
        $this->call('migrate');
    }

    protected function getModelsFields(): array
    {
        $fields = [];
        $continue = true;

        // Get existing fields if model exists
        $existingFields = [];
        if (class_exists("App\\Models\\{$this->modelName}")) {
            $model = "App\\Models\\{$this->modelName}";
            $instance = new $model;
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
                options: fn() => [
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

            if ($fieldType === 'enum') {
                $values = text(
                    label: 'Enter enum values (comma-separated)',
                    placeholder: 'e.g. draft,published,archived'
                );
                $fields[$fieldName]['values'] = array_map('trim', explode(',', $values));
            }
        }

        return $fields;
    }

    protected function addRelationToModel(string $modelName, string $relationType, string $methodName): void
    {
        $modelPath = app_path("Models/{$modelName}.php");
        $content = File::get($modelPath);

        // Generate relation method
        $relationMethod = match ($relationType) {
            'belongsTo' => $this->generateBelongsToMethod($methodName, $modelName),
            'hasMany' => $this->generateHasManyMethod($methodName, $modelName),
            default => ''
        };

        // Add relation before the last closing brace
        $content = preg_replace('/}(\s*)$/', $relationMethod . "\n}", $content);

        File::put($modelPath, $content);
        info("Added {$relationType} relation to {$modelName} model");
    }

    protected function generateBelongsToMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\BelongsTo\n" .
            "    {\n" .
            "        return \$this->belongsTo({$model}::class);\n" .
            "    }";
    }

    protected function generateHasManyMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasMany\n" .
            "    {\n" .
            "        return \$this->hasMany({$model}::class);\n" .
            "    }";
    }

    private function generateMigrationFields(array $fields): string
    {
        $tableFields = '';

        foreach ($fields as $field => $options) {
            if (str_ends_with($field, '_id')) {
                // Ask for the related table name
                $suggestedTable = Str::plural(Str::beforeLast($field, '_id'));

                $relatedTable = text(
                    label: "Enter related table name for {$field} (default: {$suggestedTable})",
                    placeholder: $suggestedTable,
                    default: $suggestedTable
                );

                // Ask for foreign key constraint type
                $constraintType = search(
                    label: "Select constraint type for {$field}",
                    options: fn() => [
                        'cascade' => 'CASCADE (delete related records)',
                        'restrict' => 'RESTRICT (prevent deletion)',
                        'nullify' => 'SET NULL (set null on deletion)',
                    ]
                );

                // Generate foreign key constraint
                $tableFields .= "\$table->foreignId('{$field}')"
                    . "->constrained('{$relatedTable}')"
                    . match ($constraintType) {
                        'cascade' => '->cascadeOnDelete()',
                        'restrict' => '->restrictOnDelete()',
                        'nullify' => '->nullOnDelete()',
                    }
                    . ($options['nullable'] ? '->nullable()' : '')
                    . ";\n";
            } else {
                // Handle non-foreign key fields
                $tableFields .= "\$table->{$options['type']}('{$field}')";
                if ($options['type'] === 'enum') {
                    $values = array_map(fn($value) => "'$value'", $options['values']);
                    $tableFields .= '->enum(' . implode(', ', $values) . ')';
                }
                if ($options['nullable']) {
                    $tableFields .= '->nullable()';
                }
                $tableFields .= ";\n";
            }
        }

        return $tableFields;
    }

    protected function handleModelCreation(): void
    {
        $fields = $this->getModelsFields();

        $this->generateModel($fields);
        $this->generateMigration($fields);
    }

    protected function generateModel(array $fields): void
    {
        $modelStub = File::get(config('rapids.stubs.migration.model'));

        $fillableStr = "'" . implode("', '", array_keys($fields)) . "'";


        $relations = $this->getModelRelations();

        $relationMethods = $this->generateRelationMethods($relations);

        $modelContent = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ fillable }}', '{{ relations }}'],
            ['App\\Models', $this->modelName, $fillableStr, $relationMethods],
            $modelStub
        );

        File::put(app_path("Models/{$this->modelName}.php"), $modelContent);
    }

    protected function getModelRelations(): array
    {
        $relations = [];
        $continue = true;

        // Check if there are any fields ending with _id
        $hasIdFields = false;
        foreach ($this->relationFields as $field => $displayField) {
            if (str_ends_with($field, '_id')) {
                $hasIdFields = true;
                break;
            }
        }

        if (!$hasIdFields) {
            info('No foreign key fields found. Skipping relations...');
            return [];
        }

        while ($continue) {
            $continue = confirm(
                label: "Would you like to add a relationship?",
                default: false
            );

            if (!$continue) {
                break;
            }

            $relationType = search(
                label: 'Select relationship type',
                options: fn() => [
                    'hasOne' => 'Has One',
                    'hasMany' => 'Has Many',
                    'belongsTo' => 'Belongs To',
                    'belongsToMany' => 'Belongs To Many',
                    'hasOneThrough' => 'Has One Through',
                    'hasManyThrough' => 'Has Many Through',
                    'morphOne' => 'Morph One',
                    'morphMany' => 'Morph Many',
                    'morphTo' => 'Morph To',
                    'morphToMany' => 'Morph To Many',
                    'morphedByMany' => 'Morphed By Many',
                ],
                placeholder: 'Select relationship type'
            );

            $relatedModel = text(
                label: 'Enter related model name',
                placeholder: 'e.g. User, Post, Comment',
                required: true
            );

            $relations[] = [
                'type' => $relationType,
                'model' => $relatedModel
            ];
        }

        return $relations;
    }

    protected function generateRelationMethods(array $relations): string
    {
        if (empty($relations)) {
            return '';
        }

        $methods = [];
        foreach ($relations as $relation) {
            $methodName = Str::camel($relation['model']);

            if ($relation['type'] === 'morphTo') {
                $methods[] = $this->generateMorphToMethod($methodName);
                continue;
            }

            $methods[] = match ($relation['type']) {
                'hasOne' => $this->generateHasOneMethod($methodName, $relation['model']),
                'hasMany' => $this->generateHasManyMethod($methodName, $relation['model']),
                'belongsTo' => $this->generateBelongsToMethod($methodName, $relation['model']),
                'belongsToMany' => $this->generateBelongsToManyMethod($methodName, $relation['model']),
                'hasOneThrough' => $this->generateHasOneThroughMethod($methodName, $relation['model']),
                'hasManyThrough' => $this->generateHasManyThroughMethod($methodName, $relation['model']),
                'morphOne' => $this->generateMorphOneMethod($methodName, $relation['model']),
                'morphMany' => $this->generateMorphManyMethod($methodName, $relation['model']),
                'morphToMany' => $this->generateMorphToManyMethod($methodName, $relation['model']),
                'morphedByMany' => $this->generateMorphedByManyMethod($methodName, $relation['model']),
                default => ''
            };
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

    protected function generateHasOneMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasOne\n" .
            "    {\n" .
            "        return \$this->hasOne({$model}::class);\n" .
            "    }";
    }

    /**
     * il dois prendre en compte deux table c'est a dire par exemple la table user_profession donc l'id de user et l'id de profession user_id et profession_id
     * @param string $methodName
     * @param string $model
     * @return string
     */
    protected function generateBelongsToManyMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\BelongsToMany\n" .
            "    {\n" .
            "        return \$this->belongsToMany({$model}::class);\n" .
            "    }";
    }

    protected function generateHasOneThroughMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasOneThrough\n" .
            "    {\n" .
            "        return \$this->hasOneThrough({$model}::class, Through::class);\n" .
            "    }";
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

    protected function generateMigration(array $fields): void
    {
        $migrationName = 'create_' . Str::snake(Str::pluralStudly($this->modelName)) . '_table';
        $migrationFile = database_path("migrations/" . date('Y_m_d_His_') . $migrationName . '.php');

        $stub = File::get(config('rapids.stubs.migration.migration'));

        $tableFields = $this->generateMigrationFields($fields);

        $migrationContent = str_replace(
            ['{{ table }}', '{{ fields }}'],
            [Str::snake(Str::pluralStudly($this->modelName)), $tableFields],
            $stub
        );

        File::put($migrationFile, $migrationContent);
    }

    protected function generateFactory(): void
    {
        $factoryStub = File::get(config('rapids.stubs.migration.factory'));
        $fields = $this->getModelFields();
        $factoryFields = [];

        foreach ($fields as $field => $type) {
            // Check if this is a relationship field
            if (str_ends_with($field, '_id')) {
                // Extract suggested model name from field (e.g., user_id -> User)
                $suggestedModel = Str::studly(Str::beforeLast($field, '_id'));

                $relatedModel = text(
                    label: "Enter related model name for {$field}",
                    placeholder: $suggestedModel,
                    default: $suggestedModel,
                    required: true
                );

                // Generate relationship factory
                $factoryFields[] = "'{$field}' => \\App\\Models\\{$relatedModel}::factory(),";
            } else {
                // Handle regular fields with more realistic fake data
                $factoryFields[] = match ($type) {
                    'string' => "'{$field}' => \$this->faker->words(3, true),",
                    'text' => "'{$field}' => \$this->faker->paragraph,",
                    'integer' => "'{$field}' => \$this->faker->numberBetween(1, 1000),",
                    'bigInteger' => "'{$field}' => \$this->faker->numberBetween(1000, 9999999),",
                    'float', 'decimal' => "'{$field}' => \$this->faker->randomFloat(2, 1, 1000),",
                    'boolean' => "'{$field}' => \$this->faker->boolean,",
                    'date' => "'{$field}' => \$this->faker->date(),",
                    'datetime', 'timestamp' => "'{$field}' => \$this->faker->dateTime(),",
                    'json' => "'{$field}' => ['key' => \$this->faker->word],",
                    'enum' => "'{$field}' => \$this->faker->randomElement(['" . implode("', '", $options['values'] ?? []) . "']),",
                    'uuid' => "'{$field}' => \$this->faker->uuid,",
                    'email' => "'{$field}' => \$this->faker->safeEmail,",
                    'phone' => "'{$field}' => \$this->faker->phoneNumber,",
                    'url' => "'{$field}' => \$this->faker->url,",
                    'code' => "'{$field}' => \$this->faker->unique()->bothify('CODE-####'),",
                    default => "'{$field}' => \$this->faker->word,",
                };
            }
        }

        $factoryContent = str_replace(
            ['{{ namespace }}', '{{ model }}', '{{ fields }}'],
            ['Database\\Factories', $this->modelName, implode("\n", $factoryFields)],
            $factoryStub
        );

        File::put(database_path("factories/{$this->modelName}Factory.php"), $factoryContent);
    }

    protected function getModelFields(): array
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

        $instance = new $modelClass;
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

    protected function generateSeeder(): void
    {
        $seederStub = File::get(config('rapids.stubs.migration.seeder'));

        $seederContent = str_replace(
            ['{{ class }}'],
            ["{$this->modelName}Seeder"],
            $seederStub
        );

        File::put(database_path("seeders/{$this->modelName}Seeder.php"), $seederContent);
    }
}
