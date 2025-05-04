<?php

declare(strict_types=1);

namespace Rapids\Rapids\Concerns;

use Exception;
use Illuminate\Support\Str;
use Rapids\Rapids\Constants\LaravelConstants;
use Rapids\Rapids\Contract\FileSystemInterface;
use Rapids\Rapids\Contract\ModelGeneratorInterface;
use Rapids\Rapids\Contract\RelationshipServiceInterface;
use Rapids\Rapids\Contract\ServiceInterface;
use Rapids\Rapids\Domain\Model\ModelDefinition;
use RuntimeException;

final readonly class ModelGenerator implements ModelGeneratorInterface
{
    public function __construct(
        private FileSystemInterface $fileSystem,
        private RelationshipServiceInterface $relationshipService,
        private ServiceInterface $promptService
    ) {
    }

    public function generateModel(ModelDefinition $modelDefinition): void
    {
        $modelName = $modelDefinition->getName();
        $fields = $modelDefinition->getFields();

        try {
            $modelContent = $this->buildModelContent($modelDefinition);

            $modelPath = $this->getModelPath($modelName);
            $this->fileSystem->put($modelPath, $modelContent);

            $this->processRelationships($modelDefinition);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to generate model: {$e->getMessage()}", 0, $e);
        }
    }

    private function buildModelContent(ModelDefinition $modelDefinition): string
    {
        $modelName = $modelDefinition->getName();
        $fields = $modelDefinition->getFields();
        $relations = [];
        $protectionType = $this->promptService->select(
            'How would you like to protect your model attributes?',
            [
                'fillable' => 'Use $fillable (explicitly allow fields)',
                'guarded' => 'Use $guarded (explicitly deny fields)'
            ]
        );

        $fieldNames = array_keys($fields);

        // Generate protection array string
        $protectionStr = match ($protectionType) {
            'fillable' => "\n    protected \$fillable = ['" . implode("', '", $fieldNames) . "'];",
            'guarded' => "\n    protected \$guarded = [];" // Empty guarded means all fields are mass assignable
        };

        $useStatements = 'use Illuminate\\Database\\Eloquent\\Model;';
        $traits = '';
        $casts = []; // Initialize casts array

        if ($modelDefinition->useSoftDeletes()) {
            $useStatements .= "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;";
            $traits = "\n    use SoftDeletes;"; // Indent trait usage
        }

        foreach ($fields as $field => $options) {
            if (str_ends_with($field, '_id')) {
                $relatedModel = ucfirst(Str::beforeLast($field, '_id'));
                $relations[] = [
                    'type' => 'belongsTo',
                    'model' => $relatedModel
                ];
            } elseif ($options['type'] === 'enum') {
                // Add Enum cast
                $enumName = Str::studly($modelName) . Str::studly($field) . 'Enum';
                $enumNamespace = 'App\\Enums';
                // Add use statement only if not already added (e.g., multiple enums)
                $useStatementToAdd = "\nuse {$enumNamespace}\\{$enumName};";
                if (!str_contains($useStatements, $useStatementToAdd)) {
                    $useStatements .= $useStatementToAdd;
                }
                $casts[] = "'{$field}' => {$enumName}::class";
            }
            // Add other casts if needed, e.g., for date/datetime, json, boolean
            elseif (in_array($options['type'], ['date', 'datetime', 'timestamp'])) {
                $castType = ($options['type'] === 'date') ? 'date' : 'datetime';
                $casts[] = "'{$field}' => '{$castType}'";
            } elseif ($options['type'] === 'json') {
                $casts[] = "'{$field}' => 'array'"; // Ou 'object' selon le besoin
            } elseif ($options['type'] === 'boolean') {
                $casts[] = "'{$field}' => 'boolean'";
            } elseif ($options['type'] === 'uuid') {
                $useStatements .= "\nuse Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;";
                $traits .= $traits ? "\n    use HasUuids;" : "\n    use HasUuids;";
            }
        }

        $relations = array_merge($relations, $modelDefinition->getRelations());
        $modelStubPath = $this->getStubPath();
        if (!$this->fileSystem->exists($modelStubPath)) {
            throw new RuntimeException("Model stub not found at: {$modelStubPath}");
        }
        $modelStub = $this->fileSystem->get($modelStubPath);
        $relationMethods = $this->relationshipService->generateRelationMethods($modelName, $relations);

        // Generate casts string
        $castsStr = '';
        if (!empty($casts)) {
            $castsStr = "\n\n    protected \$casts = [\n        " . implode(",\n        ", $casts) . "\n    ];";
        }

        return str_replace(
            ['{{ namespace }}', '{{ use }}', '{{ class }}', '{{ traits }}', '{{ protection }}', '{{ casts }}', '{{ relations }}'],
            ['App\\Models', $useStatements, $modelName, $traits, $protectionStr, $castsStr, $relationMethods],
            $modelStub
        );
    }

    private function getStubPath(): string
    {
        return config('rapids.stubs.migration.model');
    }

    private function getModelPath(string $modelName): string
    {
        return app_path("Models/{$modelName}.php");
    }

    private function processRelationships(ModelDefinition $modelDefinition): void
    {
        $modelName = $modelDefinition->getName();
        $fields = $modelDefinition->getFields();
        $relations = $modelDefinition->getRelations();

        foreach ($fields as $field => $options) {
            if (str_ends_with($field, '_id')) {
                $this->processForeignKeyRelation($modelName, $field);
            }
        }

        $this->promptForAdditionalRelations($modelName);
    }

    private function processForeignKeyRelation(string $modelName, string $field): void
    {
        $relatedModelName = $this->promptService->text(
            "Enter related model name for {$field}",
            'e.g. User for user_id',
            true
        );

        $currentModelRelation = $this->promptService->searchRelationshipType(
            "Select relationship type for {$modelName} to {$relatedModelName}"
        );

        $inverseRelation = $this->promptService->searchInverseRelationshipType(
            "Select inverse relationship type for {$relatedModelName} to {$modelName}"
        );

        if ('none' !== $inverseRelation) {
            $this->addRelationToRelatedModel($relatedModelName, $modelName, $inverseRelation);
        }
    }

    private function addRelationToRelatedModel(string $relatedModelName, string $currentModelName, string $relationType): void
    {
        $modelPath = app_path("Models/{$relatedModelName}.php");

        if (!$this->fileSystem->exists($modelPath)) {
            $this->promptService->info("Related model file not found: {$modelPath}");
            return;
        }

        $content = $this->fileSystem->get($modelPath);

        $methodName = $this->relationshipService->getRelationMethodName($relationType, $currentModelName);

        $relationMethod = $this->relationshipService->generateRelationMethod(
            $relatedModelName,
            $relationType,
            $methodName,
            $currentModelName
        );

        if (str_contains($content, "function {$methodName}(")) {
            $this->promptService->info("Relation method {$methodName}() already exists in {$relatedModelName} model");
            return;
        }

        $content = preg_replace('/}(\s*)$/', "\n    {$relationMethod}\n}", $content);

        $this->fileSystem->put($modelPath, $content);
        $this->promptService->info("Added {$relationType} relation from {$relatedModelName} to {$currentModelName}");
    }

    private function promptForAdditionalRelations(string $modelName): void
    {
        while ($this->promptService->confirm("Would you like to add another relationship?", false)) {
            $relationType = $this->promptService->searchRelationshipType('Select relationship type');

            $relatedModel = $this->promptService->text(
                'Enter related model name',
                'e.g. User, Post, Comment',
                true
            );

            $inverseRelation = $this->promptService->searchInverseRelationshipType(
                "Select inverse relationship type for {$relatedModel} to {$modelName}"
            );

            if ('none' !== $inverseRelation) {
                $this->addRelationToRelatedModel($relatedModel, $modelName, $inverseRelation);
            }
        }
    }
}
