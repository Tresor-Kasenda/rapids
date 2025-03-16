```php

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

        // Get existing fields from the model's table
        $model = "App\\Models\\{$this->modelName}";
        $instance = new $model;
        $tableName = $instance->getTable();
        $existingColumns = Schema::getColumnListing($tableName);

        while ($continue) {
            $fieldName = text(
                label: 'Enter field name (or press enter to finish)',
                placeholder: 'e.g. name, email, phone',
            );

            if (empty($fieldName)) {
                break;
            }

            // Check if field already exists
            if (in_array($fieldName, $existingColumns)) {
                info("Field '{$fieldName}' already exists in the model. Please enter a different field name.");
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
        $relations = $this->getModelRelations();

        $this->generateModel($fields, $relations);
        $this->generateMigration($fields);
        $this->generateFactory();
        $this->generateSeeder();
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

    protected function generateModel(array $fields, array $relations): void
    {
        $modelStub = File::get(config('rapids.stubs.migration.model'));

        $fillable = array_keys($fields);
        $fillableStr = "'" . implode("', '", $fillable) . "'";

        $relationMethods = $this->generateRelationMethods($relations);

        $modelContent = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ fillable }}', '{{ relations }}'],
            ['App\\Models', $this->modelName, $fillableStr, $relationMethods],
            $modelStub
        );

        File::put(app_path("Models/{$this->modelName}.php"), $modelContent);
    }

    protected function generateRelationMethods(array $relations): string
    {
        if (empty($relations)) {
            return '';
        }

        $methods = [];
        foreach ($relations as $relation) {
            $methodName = Str::camel($relation['model']);
            $methods[] = match ($relation['type']) {
                'hasOne' => $this->generateHasOneMethod($methodName, $relation['model']),
                'hasMany' => $this->generateHasManyMethod($methodName, $relation['model']),
                'belongsTo' => $this->generateBelongsToMethod($methodName, $relation['model']),
                'belongsToMany' => $this->generateBelongsToManyMethod($methodName, $relation['model']),
                'hasOneThrough' => $this->generateHasOneThroughMethod($methodName, $relation['model']),
                'hasManyThrough' => $this->generateHasManyThroughMethod($methodName, $relation['model']),
                default => ''
            };
        }

        return implode("\n\n    ", array_filter($methods));
    }

    protected function generateHasOneMethod(string $methodName, string $model): string
    {
        return "public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasOne\n" .
            "    {\n" .
            "        return \$this->hasOne({$model}::class);\n" .
            "    }";
    }

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

        foreach ($fields as $field => $options) {
            // Check if this is a relationship field
            if (str_ends_with($options, '_id')) {
                // Extract suggested model name from field (e.g., user_id -> User)
                $suggestedModel = Str::studly(Str::beforeLast($options, '_id'));

                $relatedModel = text(
                    label: "Enter related model name for {$options}",
                    placeholder: $suggestedModel,
                    default: $suggestedModel,
                    required: true
                );

                // Generate relationship factory
                $factoryFields[] = "'{$options}' => \\App\\Models\\{$relatedModel}::factory(),";
            } else {
                // Handle regular fields based on their type
                $factoryFields[] = match ($options['type']) {
                    'string', 'text' => "'{$field}' => \$this->faker->word,",
                    'integer', 'bigInteger' => "'{$field}' => \$this->faker->numberBetween(1, 100),",
                    'float', 'decimal' => "'{$field}' => \$this->faker->randomFloat(2, 1, 100),",
                    'boolean' => "'{$field}' => \$this->faker->boolean,",
                    'date' => "'{$field}' => \$this->faker->date(),",
                    'datetime', 'timestamp' => "'{$field}' => \$this->faker->dateTime(),",
                    'json' => "'{$field}' => [],",
                    'enum' => "'{$field}' => \$this->faker->randomElement(['" . implode("', '", $options['values'] ?? []) . "']),",
                    default => "'{$field}' => null,",
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
        if (!class_exists("App\\Models\\{$this->modelName}")) {
            $this->refreshApplication();

            // Recheck if model exists after refresh
            if (!class_exists("App\\Models\\{$this->modelName}")) {
                throw new RuntimeException("Model {$this->modelName} could not be loaded.");
            }
        }

        $model = "App\\Models\\{$this->modelName}";
        $instance = new $model;
        $tableName = $instance->getTable();
        // Get all table columns
        $columns = Schema::getColumnListing($tableName);


        $fields = [];

        // Get field types
        foreach ($columns as $column) {
            // Skip internal columns
            if (in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $columnType = Schema::getColumnType($tableName, $column);

            // Map database types to simpler types
            $fields[$column] = match ($columnType) {
                'bigint', 'int', 'integer', 'smallint', 'tinyint' => 'integer',
                'decimal', 'double', 'float' => 'float',
                'boolean', 'tinyint(1)' => 'boolean',
                'date' => 'date',
                'datetime', 'timestamp' => 'datetime',
                'json' => 'json',
                'text', 'mediumtext', 'longtext' => 'text',
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

    protected function refreshApplication(): void
    {
        info('Refreshing application...');

        $commands = [
            'optimize:clear',
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'composer dump-autoload',
        ];

        collect($commands)->each(function ($command) {
            if ($command === 'composer dump-autoload') {
                info('Running composer dump-autoload...');
                exec('composer dump-autoload');
            } else {
                info("Running {$command}...");
                $this->call($command);
            }
        });

        info('Application refreshed successfully.');
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


```
