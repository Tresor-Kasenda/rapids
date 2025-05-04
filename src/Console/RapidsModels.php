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
    protected $signature = 'rapids:model {name?} {--fields=}';

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
            fn ($file) => pathinfo($file, PATHINFO_FILENAME),
            glob($modelPath.'/*.php')
        );

        $availableModels = array_filter($modelFiles, fn ($model) => class_exists("App\\Models\\{$model}"));

        $modelName = $this->argument('name') ?? text(
            label: 'Enter model name (without "App\\Models\\")',
            placeholder: 'e.g. User, Post, Product',
            required: true,
            validate: fn (string $value) => match (true) {
                mb_strlen($value) < 2 => 'The model name must be at least 2 characters.',
                ! preg_match('/^[A-Za-z]+$/', $value) => 'The model name must contain only letters.',
                default => null
            }
        );

        if (in_array($modelName, $availableModels)) {
            info("Model {$modelName} already exists.");

            $choice = search(
                label: 'What would you like to do?',
                options: fn () => [
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

        // Check if fields flag is provided
        $fieldsJson = $this->option('fields');
        if (!empty($fieldsJson)) {
            $this->handleModelCreationFromJson($fieldsJson);
        } else {
            $this->handleModelCreation();
        }
        
        info('Running migrations...');
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
                    options: fn () => [
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
                    options: fn () => [
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

        $migrationName = 'add_fields_to_'.Str::snake(Str::pluralStudly($modelName)).'_table';
        $migrationFile = database_path("migrations/".date('Y_m_d_His_').$migrationName.'.php');

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

        if ( ! File::exists($modelPath)) {
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
        
        // Ajouter un log pour vérifier la valeur
        info("SoftDelete choisi par l'utilisateur: " . ($useSoftDeletes ? 'Oui' : 'Non'));

        $modelDefinition = new ModelDefinition(
            $this->modelName,
            $generatedFields,
            $this->relationFields,
            true, // useFillable, valeur par défaut
            $useSoftDeletes // Nous passons le choix de SoftDelete
        );
        
        // Vérifier que la valeur est correctement enregistrée dans l'objet
        info("SoftDelete stocké dans ModelDefinition: " . ($modelDefinition->useSoftDeletes() ? 'Oui' : 'Non'));

        $modelGeneration->generateModel($modelDefinition);

        new MigrationGenerator($this->modelName)->generateMigration($generatedFields, $useSoftDeletes);
    }

    /**
     * Handle model creation from JSON input
     * 
     * @param string $fieldsJson JSON string containing field definitions
     * @return void
     */
    protected function handleModelCreationFromJson(string $fieldsJson): void
    {
        try {
            $fieldsData = json_decode($fieldsJson, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($fieldsData)) {
                throw new \Exception("Invalid JSON format for fields");
            }
            
            // Process fields
            $processedFields = [];
            $this->relationFields = [];
            
            foreach ($fieldsData as $fieldName => $fieldConfig) {
                // If field is a relation
                if (isset($fieldConfig['relation'])) {
                    $relationType = $fieldConfig['relation']['type'] ?? 'belongsTo';
                    $relatedModel = $fieldConfig['relation']['model'] ?? null;
                    $inverseRelation = $fieldConfig['relation']['inverse'] ?? 'none';
                    
                    if ($relatedModel) {
                        // Add relation to the model
                        $this->relationFields[$fieldName] = [
                            'type' => $relationType,
                            'model' => $relatedModel,
                        ];
                        
                        // If field is a foreign key, add it to fields
                        if ($relationType === 'belongsTo') {
                            $processedFields[Str::snake($fieldName) . '_id'] = [
                                'type' => 'foreignId',
                                'nullable' => $fieldConfig['nullable'] ?? false,
                            ];
                        }
                        
                        // Add inverse relation if specified
                        if ($inverseRelation !== 'none') {
                            $this->addRelationToModel(
                                $relatedModel,
                                $this->modelName,
                                $inverseRelation
                            );
                        }
                    }
                } else {
                    // Regular field
                    $processedFields[$fieldName] = [
                        'type' => $fieldConfig['type'] ?? 'string',
                        'nullable' => $fieldConfig['nullable'] ?? false,
                    ];
                    
                    // Add additional properties if provided
                    if (isset($fieldConfig['default'])) {
                        $processedFields[$fieldName]['default'] = $fieldConfig['default'];
                    }
                    
                    if (isset($fieldConfig['length'])) {
                        $processedFields[$fieldName]['length'] = $fieldConfig['length'];
                    }
                    
                    if (isset($fieldConfig['values']) && $fieldConfig['type'] === 'enum') {
                        $processedFields[$fieldName]['values'] = $fieldConfig['values'];
                    }
                }
            }
            
            $this->selectedFields = $processedFields;
            
            // Determine if soft deletes should be used
            $useSoftDeletes = $fieldsData['_config']['softDeletes'] ?? false;
            
            // Create the model and migration
            $fileSystem = new LaravelFileSystem();
            $relationshipService = new LaravelRelationshipService();
            $promptService = new PromptService();
            
            $modelGeneration = new ModelGenerator(
                $fileSystem,
                $relationshipService,
                $promptService
            );
            
            $modelDefinition = new ModelDefinition(
                $this->modelName,
                $processedFields,
                $this->relationFields,
                true, // useFillable, default value
                $useSoftDeletes
            );
            
            $modelGeneration->generateModel($modelDefinition);
            
            // Generate the migration
            new MigrationGenerator($this->modelName)->generateMigration($processedFields, $useSoftDeletes);
            
            info("Model {$this->modelName} created successfully from JSON input");
            
        } catch (\Exception $e) {
            $this->error("Error processing JSON input: " . $e->getMessage());
            $this->handleModelCreation(); // Fall back to interactive mode
        }
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
