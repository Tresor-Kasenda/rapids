<?php

declare(strict_types=1);

namespace Rapids\Rapids\Concerns;

use Exception;
use Illuminate\Support\Str;
use Rapids\Rapids\Contract\FileSystemInterface;
use Rapids\Rapids\Contract\ModelGeneratorInterface;
use Rapids\Rapids\Contract\RelationshipServiceInterface;
use Rapids\Rapids\Contract\ServiceInterface;
use Rapids\Rapids\Domain\Model\ModelDefinition;
use RuntimeException;

readonly class ModelGenerator implements ModelGeneratorInterface
{
    public function __construct(
        private FileSystemInterface          $fileSystem,
        private RelationshipServiceInterface $relationshipService,
        private ServiceInterface             $promptService
    )
    {
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

        if ($modelDefinition->setUseSoftDeletes(true)) {
            $useStatements .= "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;";
            $traits = "\n    use SoftDeletes;";
        }

        foreach ($fields as $field => $options) {
            if (str_ends_with($field, '_id')) {
                $relatedModel = ucfirst(Str::beforeLast($field, '_id'));
                $relations[] = [
                    'type' => 'belongsTo',
                    'model' => $relatedModel
                ];
            }
        }

        $relations = array_merge($relations, $modelDefinition->getRelations());
        $modelStub = $this->fileSystem->get($this->getStubPath());
        $relationMethods = $this->relationshipService->generateRelationMethods($modelName, $relations);

        return str_replace(
            ['{{ namespace }}', '{{ use }}', '{{ class }}', '{{ traits }}', '{{ protection }}', '{{ relations }}'],
            ['App\\Models', $useStatements, $modelName, $traits, $protectionStr, $relationMethods],
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

        if ($inverseRelation !== 'none') {
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

            if ($inverseRelation !== 'none') {
                $this->addRelationToRelatedModel($relatedModel, $modelName, $inverseRelation);
            }
        }
    }
}
