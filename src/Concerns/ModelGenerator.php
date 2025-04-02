<?php

namespace Rapids\Rapids\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Rapids\Rapids\Contract\ModelGenerationInterface;
use Rapids\Rapids\Relations\RelationshipGeneration;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

class ModelGenerator implements ModelGenerationInterface
{
    public function __construct(
        public string $modelName,
        public array  $relationFields,
    )
    {
    }

    public function generateModel(array $fields): void
    {
        $modelStub = File::get(config('rapids.stubs.migration.model'));
        $fillableStr = "'" . implode("', '", array_keys($fields)) . "'";
        $relations = [];

        // Handle automatic relations from foreign keys
        foreach ($fields as $field => $type) {
            if (str_ends_with($field, '_id')) {
                $relatedModelName = text(
                    label: "Enter related model name for {$field}",
                    placeholder: 'e.g. User for user_id',
                    required: true
                );

                $currentModelRelation = search(
                    label: "Select relationship type for {$this->modelName} to {$relatedModelName}",
                    options: fn() => [
                        'hasOne' => 'Has One',
                        'belongsTo' => 'Belongs To',
                        'belongsToMany' => 'Belongs To Many',
                        'hasMany' => 'Has Many',
                        'morphOne' => 'Morph One',
                        'morphMany' => 'Morph Many',
                        'morphTo' => 'Morph To'
                    ],
                    placeholder: 'Select relationship type'
                );

                $inverseRelation = search(
                    label: "Select inverse relationship type for {$relatedModelName} to {$this->modelName}",
                    options: fn() => [
                        'hasOne' => 'Has One',
                        'belongsTo' => 'Belongs To',
                        'belongsToMany' => 'Belongs To Many',
                        'hasMany' => 'Has Many',
                        'morphOne' => 'Morph One',
                        'morphMany' => 'Morph Many',
                        'morphTo' => 'Morph To',
                        'none' => 'No inverse relation'
                    ],
                    placeholder: 'Select inverse relationship type'
                );

                $relations[] = [
                    'type' => $currentModelRelation,
                    'model' => $relatedModelName
                ];

                if ($inverseRelation !== 'none') {
                    // Add inverse relation to the related model file
                    $this->addRelationToRelatedModel($relatedModelName, $this->modelName, $inverseRelation);
                }
            }
        }

        // Allow adding additional manual relations
        while (confirm(label: "Would you like to add another relationship?", default: false)) {
            $relationType = search(
                label: 'Select relationship type',
                options: fn() => [
                    'hasOne' => 'Has One',
                    'belongsTo' => 'Belongs To',
                    'belongsToMany' => 'Belongs To Many',
                    'hasMany' => 'Has Many',
                    'morphOne' => 'Morph One',
                    'morphMany' => 'Morph Many',
                    'morphTo' => 'Morph To',
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

            // Ask for inverse relation for manually added relations too
            $inverseRelation = search(
                label: "Select inverse relationship type for {$relatedModel} to {$this->modelName}",
                options: fn() => [
                    'hasOne' => 'Has One',
                    'belongsTo' => 'Belongs To',
                    'belongsToMany' => 'Belongs To Many',
                    'hasMany' => 'Has Many',
                    'morphOne' => 'Morph One',
                    'morphMany' => 'Morph Many',
                    'morphTo' => 'Morph To',
                    'none' => 'No inverse relation'
                ],
                placeholder: 'Select inverse relationship type'
            );

            if ($inverseRelation !== 'none') {
                // Add inverse relation to the related model file
                $this->addRelationToRelatedModel($relatedModel, $this->modelName, $inverseRelation);
            }
        }

        $relationMethods = (new RelationshipGeneration($this->modelName))
            ->generateRelationMethods($relations);

        $modelContent = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ fillable }}', '{{ relations }}'],
            ['App\\Models', $this->modelName, $fillableStr, $relationMethods],
            $modelStub
        );

        File::put(app_path("Models/{$this->modelName}.php"), $modelContent);
    }

    protected function addRelationToRelatedModel(string $relatedModelName, string $currentModelName, string $relationType): void
    {
        $modelPath = app_path("Models/{$relatedModelName}.php");

        if (!File::exists($modelPath)) {
            info("Related model file not found: {$modelPath}");
            return;
        }

        $content = File::get($modelPath);

        // Generate method name based on current model (e.g., "users" for User model if using hasMany)
        $methodName = match ($relationType) {
            'hasMany', 'belongsToMany', 'morphMany', 'morphToMany', 'morphedByMany', 'hasManyThrough' =>
            Str::camel(Str::plural($currentModelName)),
            'hasOne', 'belongsTo', 'morphOne', 'morphTo', 'hasOneThrough' =>
            Str::camel(Str::singular($currentModelName)),
            default => Str::camel($currentModelName)
        };

        $relationShips = new RelationshipGeneration($relatedModelName);

        $relationMethod = $relationShips->relationGeneration(
            $relationType,
            $methodName,
            [$currentModelName]
        );

        if (str_contains($content, "function {$methodName}(")) {
            info("Relation method {$methodName}() already exists in {$relatedModelName} model");
            return;
        }

        // Add the relation method before the last closing brace
        $content = preg_replace('/}(\s*)$/', "\n    {$relationMethod}\n}", $content);

        File::put($modelPath, $content);
        info("Added {$relationType} relation from {$relatedModelName} to {$currentModelName}");
    }
}
