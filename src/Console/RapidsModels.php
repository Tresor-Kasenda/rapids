<?php

declare(strict_types=1);

namespace Rapids\Rapids\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Rapids\Rapids\Concerns\FactoryGenerator;
use Rapids\Rapids\Concerns\MigrationGenerator;
use Rapids\Rapids\Concerns\ModelFieldsGenerator;
use Rapids\Rapids\Concerns\ModelGenerator;
use Rapids\Rapids\Concerns\SeederGenerator;
use Rapids\Rapids\Domain\Model\ModelDefinition;
use Rapids\Rapids\Infrastructure\Laravel\LaravelFileSystem;
use Rapids\Rapids\Infrastructure\Laravel\LaravelRelationshipService;
use Rapids\Rapids\Infrastructure\Laravel\PromptService;
use Rapids\Rapids\Relations\RelationshipGeneration;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

final class RapidsModels extends Command
{
    protected $signature = 'rapids:model {name?}';

    protected $description = '
        Create a new model with fields, relations, migration, factory, and seeder in one go.
    ';

    protected string $modelName;

    protected array $relationFields = [];

    protected array $selectedFields = [];

    /**
     * @throws FileNotFoundException
     */
    public function handle(): void
    {
        $modelPath = app_path('Models');
        $modelFiles = array_map(
            fn($file) => pathinfo($file, PATHINFO_FILENAME),
            glob($modelPath . '/*.php')
        );

        $availableModels = array_filter($modelFiles, fn($model) => class_exists("App\\Models\\{$model}"));

        $modelName = $this->argument('name') ?? text(
            label: 'Enter model name (without "App\\Models\\")',
            placeholder: 'e.g. User, Post, Product',
            required: true,
            validate: fn(string $value) => match (true) {
                mb_strlen($value) < 2 => 'The model name must be at least 2 characters.',
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
        new SeederGenerator($this->modelName)->generateSeeder();
        info('Model created successfully.');
    }

    protected function handleExistingModel(bool|array|string $modelName): void
    {
        $this->modelName = $modelName;
        info("Adding new migration for {$modelName}");

        $fields = new ModelFieldsGenerator($this->modelName)->generate();

        foreach ($fields as $field => &$options) {
            $options['nullable'] = true;
        }

        unset($options);
        foreach ($fields as $field => $options) {
            if (str_ends_with($field, '_id')) {
                $relatedModelName = text(
                    label: "Enter related model name for {$field}",
                    placeholder: 'e.g. User for user_id',
                    required: true
                );

                $currentModelRelation = search(
                    label: "Select relationship type for {$this->modelName} to {$relatedModelName}",
                    options: fn() => [
                        'belongsTo' => 'Belongs To',
                        'hasOne' => 'Has One',
                        'hasMany' => 'Has Many',
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

                $inverseRelation = search(
                    label: "Select inverse relationship type for {$relatedModelName} to {$this->modelName}",
                    options: fn() => [
                        'hasMany' => 'Has Many',
                        'hasOne' => 'Has One',
                        'belongsTo' => 'Belongs To',
                        'belongsToMany' => 'Belongs To Many',
                        'hasOneThrough' => 'Has One Through',
                        'hasManyThrough' => 'Has Many Through',
                        'morphOne' => 'Morph One',
                        'morphMany' => 'Morph Many',
                        'morphTo' => 'Morph To',
                        'morphToMany' => 'Morph To Many',
                        'morphedByMany' => 'Morphed By Many',
                        'none' => 'No inverse relation'
                    ],
                    placeholder: 'Select inverse relationship type'
                );

                $this->addRelationToModel(
                    $this->modelName,
                    $relatedModelName,
                    $currentModelRelation
                );

                if ('none' !== $inverseRelation) {
                    $this->addRelationToModel(
                        $relatedModelName,
                        $this->modelName,
                        $inverseRelation
                    );
                }
            }
        }

        $migrationName = 'add_fields_to_' . Str::snake(Str::pluralStudly($modelName)) . '_table';
        $migrationFile = database_path("migrations/" . date('Y_m_d_His_') . $migrationName . '.php');

        $stub = File::get(config('rapids.stubs.migration.alter'));
        $tableFields = new MigrationGenerator($this->modelName)
            ->generateMigrationFields($fields);

        $migrationContent = str_replace(
            ['{{ table }}', '{{ fields }}'],
            [Str::snake(Str::pluralStudly($modelName)), $tableFields],
            $stub
        );

        File::put($migrationFile, $migrationContent);
        info('Migration created successfully.');
        $this->call('migrate');
    }

    protected function addRelationToModel(string $modelName, string $relatedModelName, string $relationType): void
    {
        $modelPath = app_path("Models/{$modelName}.php");

        if (!File::exists($modelPath)) {
            info("Model file not found: {$modelPath}");
            return;
        }

        $content = File::get($modelPath);

        $methodName = Str::camel(Str::singular($relatedModelName));

        $relationShips = new RelationshipGeneration($this->modelName);

        $relationMethod = $relationShips->relationGeneration(
            $relationType,
            $methodName,
            (array)$relatedModelName
        );

        if (empty($relationMethod)) {
            info("Invalid relationship type: {$relationType}");
            return;
        }

        if (str_contains($content, "function {$methodName}(")) {
            info("Relation method {$methodName}() already exists in {$modelName} model");
            return;
        }

        $content = preg_replace('/}(\s*)$/', "\n    {$relationMethod}\n}", $content);

        File::put($modelPath, $content);
        info("Added {$relationType} relation from {$modelName} to {$relatedModelName}");
    }

    protected function handleModelCreation(): void
    {
        $fields = new ModelFieldsGenerator($this->modelName);

        $fileSystem = new LaravelFileSystem();
        $relationshipService = new LaravelRelationshipService();
        $promptService = new PromptService();

        $modelGeneration = new ModelGenerator(
            $fileSystem,
            $relationshipService,
            $promptService
        );

        $generatedFields = $fields->generate();
        $this->selectedFields = $generatedFields; // Store for later use in factory generation

        $useSoftDeletes = confirm(
            label: 'Would you like to add soft delete functionality?',
            default: false
        );

        $modelDefinition = new ModelDefinition(
            $this->modelName,
            $generatedFields,
            $this->relationFields,
            $useSoftDeletes
        );

        $modelGeneration->generateModel($modelDefinition);

        new MigrationGenerator($this->modelName)->generateMigration($generatedFields, $useSoftDeletes);
    }

    /**
     * @throws FileNotFoundException
     */
    protected function generateFactory(): void
    {
        $factories = new FactoryGenerator(
            $this->modelName,
            $this->selectedFields,
            $this->relationFields
        );
        $factories->generateFactory();
    }
}
