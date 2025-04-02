<?php

declare(strict_types=1);

namespace Rapids\Rapids\Concerns;

use Exception;
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
            // Generate model content
            $modelContent = $this->buildModelContent($modelDefinition);

            // Create model file
            $modelPath = $this->getModelPath($modelName);
            $this->fileSystem->put($modelPath, $modelContent);

            // Process relationships
            $this->processRelationships($modelDefinition);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to generate model: {$e->getMessage()}", 0, $e);
        }
    }

    private function buildModelContent(ModelDefinition $modelDefinition): string
    {
        $modelName = $modelDefinition->getName();
        $fields = $modelDefinition->getFields();
        $relations = $modelDefinition->getRelations();

        // Get model stub
        $modelStub = $this->fileSystem->get($this->getStubPath());

        // Build fillable fields string
        $fillableStr = "'" . implode("', '", array_keys($fields)) . "'";

        // Generate relation methods
        $relationMethods = $this->relationshipService->generateRelationMethods($modelName, $relations);

        // Replace placeholders
        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ fillable }}', '{{ relations }}'],
            ['App\\Models', $modelName, $fillableStr, $relationMethods],
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

        // Process foreign key fields for automatic relationships
        foreach ($fields as $field => $options) {
            if (str_ends_with($field, '_id')) {
                $this->processForeignKeyRelation($modelName, $field);
            }
        }

        // Allow adding manual relationships
        $this->promptForAdditionalRelations($modelName);
    }

    private function processForeignKeyRelation(string $modelName, string $field): void
    {
        // Get related model name
        $relatedModelName = $this->promptService->text(
            "Enter related model name for {$field}",
            'e.g. User for user_id',
            true
        );

        // Define current model's relationship to related model
        $currentModelRelation = $this->promptService->searchRelationshipType(
            "Select relationship type for {$modelName} to {$relatedModelName}"
        );

        // Define inverse relationship
        $inverseRelation = $this->promptService->searchInverseRelationshipType(
            "Select inverse relationship type for {$relatedModelName} to {$modelName}"
        );

        // Add relationship to current model
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

        // Generate method name based on relationship type and current model name
        $methodName = $this->relationshipService->getRelationMethodName($relationType, $currentModelName);

        // Generate relation method
        $relationMethod = $this->relationshipService->generateRelationMethod(
            $relatedModelName,
            $relationType,
            $methodName,
            $currentModelName
        );

        // Check if method already exists
        if (str_contains($content, "function {$methodName}(")) {
            $this->promptService->info("Relation method {$methodName}() already exists in {$relatedModelName} model");
            return;
        }

        // Add the relation method before the last closing brace
        $content = preg_replace('/}(\s*)$/', "\n    {$relationMethod}\n}", $content);

        // Save updated model
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

            // Ask for inverse relation
            $inverseRelation = $this->promptService->searchInverseRelationshipType(
                "Select inverse relationship type for {$relatedModel} to {$modelName}"
            );

            if ($inverseRelation !== 'none') {
                $this->addRelationToRelatedModel($relatedModel, $modelName, $inverseRelation);
            }
        }
    }
}
