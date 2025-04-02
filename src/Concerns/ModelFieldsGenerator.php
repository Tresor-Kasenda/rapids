<?php

declare(strict_types=1);

namespace Rapids\Rapids\Concerns;

use Rapids\Rapids\Contract\ModelInspectorInterface;
use Rapids\Rapids\Contract\PromptServiceInterface;
use Rapids\Rapids\Infrastructure\Laravel\LaravelModelInspector;
use Rapids\Rapids\Infrastructure\Laravel\LaravelPromptService;

class ModelFieldsGenerator
{
    private ModelInspectorInterface $modelInspector;
    private PromptServiceInterface $promptService;
    private array $fields = [];
    private array $usedFieldNames = [];

    public function __construct(
        private readonly string  $modelName,
        ?ModelInspectorInterface $modelInspector = null,
        ?PromptServiceInterface  $promptService = null
    )
    {
        $this->modelInspector = $modelInspector ?? new LaravelModelInspector();
        $this->promptService = $promptService ?? new LaravelPromptService();
    }

    public function generate(): array
    {
        $this->fields = [];
        $this->usedFieldNames = [];
        $existingFields = $this->modelInspector->getExistingFields($this->modelName);

        while (true) {
            $this->displayCurrentFields();

            $action = $this->promptService->select(
                'What would you like to do?',
                [
                    'add' => 'Add a new field',
                    'edit' => 'Edit an existing field',
                    'delete' => 'Delete a field',
                    'done' => 'Done - proceed with these fields'
                ],
                empty($this->fields) ? 'add' : null
            );

            if ($action === 'done') {
                break;
            }

            if ($action === 'delete') {
                $this->deleteField();
                continue;
            }

            if ($action === 'edit') {
                $this->editField();
                continue;
            }

            $this->addField($existingFields);
        }

        return $this->fields;
    }

    private function displayCurrentFields(): void
    {
        if (empty($this->fields)) {
            return;
        }

        $tableData = [];
        foreach ($this->fields as $name => $options) {
            $tableData[] = [
                'name' => $name,
                'type' => $options['type'],
                'nullable' => $options['nullable'] ? 'Yes' : 'No',
                'values' => isset($options['values']) ? implode(',', $options['values']) : '-'
            ];
        }

        $this->promptService->table(['Field', 'Type', 'Nullable', 'Enum Values'], $tableData);
    }

    private function deleteField(): void
    {
        if (empty($this->fields)) {
            $this->promptService->error('No fields to delete.');
            return;
        }

        $fieldToDelete = $this->promptService->search(
            'Select field to delete',
            array_keys($this->fields)
        );

        unset($this->fields[$fieldToDelete]);
        $this->usedFieldNames = array_diff($this->usedFieldNames, [$fieldToDelete]);
        $this->promptService->info("Field '{$fieldToDelete}' has been deleted.");
    }

    private function editField(): void
    {
        if (empty($this->fields)) {
            $this->promptService->error('No fields to edit.');
            return;
        }

        $fieldToEdit = $this->promptService->search(
            'Select field to edit',
            array_keys($this->fields)
        );

        $this->fields[$fieldToEdit] = $this->collectFieldOptions($fieldToEdit, [
            'type' => $this->fields[$fieldToEdit]['type'],
            'nullable' => $this->fields[$fieldToEdit]['nullable']
        ]);

        $this->promptService->info("Field '{$fieldToEdit}' has been updated.");
    }

    private function collectFieldOptions(string $fieldName, array $defaults = []): array
    {
        $fieldType = $this->promptService->search(
            "Select field type" . (!empty($defaults) ? " for '{$fieldName}'" : ""),
            [
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
                'foreignId' => 'Foreign ID (UNSIGNED BIGINT)',
            ],
            $defaults['type'] ?? null
        );

        $nullable = $this->promptService->confirm(
            "Is this field nullable?",
            $defaults['nullable'] ?? false
        );

        $options = [
            'type' => $fieldType,
            'nullable' => $nullable
        ];

        if ('enum' === $fieldType) {
            $defaultValues = isset($defaults['values']) ? implode(',', $defaults['values']) : '';
            $values = $this->promptService->text(
                'Enter enum values (comma-separated)',
                'e.g. draft,published,archived',
                $defaultValues
            );
            $options['values'] = array_map('trim', explode(',', $values));
        }

        return $options;
    }

    private function addField(array $existingFields): void
    {
        $fieldName = $this->promptService->text(
            'Enter field name',
            'e.g. name, email, phone'
        );

        if (empty($fieldName)) {
            return;
        }

        if (in_array($fieldName, $this->usedFieldNames)) {
            $this->promptService->error("You have already added a field named '{$fieldName}'. Please use a different name.");
            return;
        }

        if (in_array($fieldName, $existingFields)) {
            $this->promptService->error("Field '{$fieldName}' already exists in the model.");
            return;
        }

        $this->fields[$fieldName] = $this->collectFieldOptions($fieldName);
        $this->usedFieldNames[] = $fieldName;
    }
}
