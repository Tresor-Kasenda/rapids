<?php

declare(strict_types=1);

namespace Rapids\Rapids\Relations;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

final class ModelRelation
{
    public function __construct(
        public array $relationFields
    ) {
    }

    public function getModelRelations(): array
    {
        $relations = [];
        $hasIdFields = false;

        // Check for foreign key fields
        foreach ($this->relationFields as $field => $displayField) {
            if (str_ends_with($field, '_id')) {
                $hasIdFields = true;

                $relatedModelName = text(
                    label: "Enter related model name for {$field}",
                    placeholder: 'e.g. User for user_id',
                    required: true
                );

                // Get relationship type for current model
                $currentModelRelation = search(
                    label: "Select relationship type for current model to {$relatedModelName}",
                    options: fn () => [
                        'hasOne' => 'Has One',
                        'belongsTo' => 'Belongs To',
                        'belongsToMany' => 'Belongs To Many',
                        'hasMany' => 'Has Many',
                        'hasOneThrough' => 'Has One Through',
                        'hasManyThrough' => 'Has Many Through',
                        'morphOne' => 'Morph One',
                        'morphMany' => 'Morph Many',
                        'morphTo' => 'Morph To',
                        'morphToMany' => 'Morph To Many',
                        'morphedByMany' => 'Morphed By Many',
                        'none' => 'No inverse relation'
                    ],
                    placeholder: 'Select relationship type'
                );

                // Get inverse relationship
                $inverseRelation = search(
                    label: "Select inverse relationship type from {$relatedModelName}",
                    options: fn () => [
                        'hasOne' => 'Has One',
                        'belongsTo' => 'Belongs To',
                        'belongsToMany' => 'Belongs To Many',
                        'hasMany' => 'Has Many',
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

                // Add primary relation
                $relations[] = [
                    'type' => $currentModelRelation,
                    'model' => $relatedModelName,
                    'field' => $field
                ];

                // Add inverse relation if selected
                if ('none' !== $inverseRelation) {
                    $relations[] = [
                        'type' => $inverseRelation,
                        'model' => $relatedModelName,
                        'inverse' => true,
                        'field' => $field
                    ];
                }
            }
        }

        // Manage relations (add, edit, delete)
        $this->manageRelations($relations);

        if (empty($relations)) {
            info('No relationships defined.');
        }

        return $relations;
    }

    private function manageRelations(array &$relations): void
    {
        while (true) {
            // Display current relations
            if ( ! empty($relations)) {
                info("Current relationships:");
                $tableData = [];
                foreach ($relations as $index => $relation) {
                    $tableData[] = [
                        'index' => $index,
                        'type' => $relation['type'],
                        'model' => $relation['model'],
                        'inverse' => isset($relation['inverse']) ? 'Yes' : 'No',
                        'field' => $relation['field'] ?? 'N/A'
                    ];
                }
                table(['#', 'Type', 'Related Model', 'Inverse', 'Field'], $tableData);
            }

            // Choose action
            $action = select(
                'What would you like to do with relationships?',
                [
                    'add' => 'Add a new relationship',
                    'edit' => 'Edit an existing relationship',
                    'delete' => 'Delete a relationship',
                    'done' => 'Done - proceed with these relationships'
                ],
                default: empty($relations) ? 'add' : null
            );

            if ('done' === $action) {
                break;
            } elseif ('delete' === $action && ! empty($relations)) {
                $indexToDelete = select(
                    label: 'Select relationship to delete',
                    options: array_map(fn ($i) => (string)$i, array_keys($relations))
                );
                unset($relations[$indexToDelete]);
                $relations = array_values($relations); // Re-index array
                info("Relationship has been deleted.");
                continue;
            } elseif ('edit' === $action && ! empty($relations)) {
                $indexToEdit = select(
                    label: 'Select relationship to edit',
                    options: array_map(fn ($i) => (string)$i, array_keys($relations))
                );

                $relationType = search(
                    label: 'Select new relationship type',
                    options: fn () => [
                        'hasOne' => 'Has One',
                        'belongsTo' => 'Belongs To',
                        'belongsToMany' => 'Belongs To Many',
                        'hasMany' => 'Has Many',
                        'hasOneThrough' => 'Has One Through',
                        'hasManyThrough' => 'Has Many Through',
                        'morphOne' => 'Morph One',
                        'morphMany' => 'Morph Many',
                        'morphTo' => 'Morph To',
                        'morphToMany' => 'Morph To Many',
                        'morphedByMany' => 'Morphed By Many',
                    ] ?? $relations[$indexToEdit]['type'],
                    placeholder: "Select new relationship type",
                    scroll: 10
                );

                $relatedModel = text(
                    label: 'Enter related model name',
                    placeholder: 'e.g. User, Post, Comment',
                    default: $relations[$indexToEdit]['model'],
                    required: true
                );

                $relations[$indexToEdit]['type'] = $relationType;
                $relations[$indexToEdit]['model'] = $relatedModel;

                info("Relationship has been updated.");
                continue;
            } elseif ('add' === $action) {
                $relationType = search(
                    label: 'Select relationship type',
                    options: fn () => [
                        'hasOne' => 'Has One',
                        'belongsTo' => 'Belongs To',
                        'belongsToMany' => 'Belongs To Many',
                        'hasMany' => 'Has Many',
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

                // Ask if they want to add an inverse relation
                if (confirm(label: "Would you like to add an inverse relationship from {$relatedModel}?", default: true)) {
                    $inverseRelation = search(
                        label: "Select inverse relationship type from {$relatedModel}",
                        options: fn () => [
                            'hasOne' => 'Has One',
                            'belongsTo' => 'Belongs To',
                            'belongsToMany' => 'Belongs To Many',
                            'hasMany' => 'Has Many',
                            'hasOneThrough' => 'Has One Through',
                            'hasManyThrough' => 'Has Many Through',
                            'morphOne' => 'Morph One',
                            'morphMany' => 'Morph Many',
                            'morphTo' => 'Morph To',
                            'morphToMany' => 'Morph To Many',
                            'morphedByMany' => 'Morphed By Many',
                        ],
                        placeholder: 'Select inverse relationship type'
                    );

                    $relations[] = [
                        'type' => $inverseRelation,
                        'model' => $relatedModel,
                        'inverse' => true
                    ];
                }
            }
        }
    }
}
