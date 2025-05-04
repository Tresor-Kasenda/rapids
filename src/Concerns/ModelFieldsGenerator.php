<?php

declare(strict_types=1);

namespace Rapids\Rapids\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Rapids\Rapids\Contract\ModelInspectorInterface;
use Rapids\Rapids\Contract\PromptServiceInterface;
use Rapids\Rapids\Infrastructure\Laravel\LaravelModelInspector;
use Rapids\Rapids\Infrastructure\Laravel\LaravelPromptService;

final class ModelFieldsGenerator
{
    private ModelInspectorInterface $modelInspector;
    private PromptServiceInterface $promptService;
    private array $fields = [];
    private array $usedFieldNames = [];

    public function __construct(
        private readonly string  $modelName,
        ?ModelInspectorInterface $modelInspector = null,
        ?PromptServiceInterface  $promptService = null
    ) {
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

            if ('done' === $action) {
                break;
            }

            if ('delete' === $action) {
                $this->deleteField();
                continue;
            }

            if ('edit' === $action) {
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

        // Store original type and values to check if they changed
        $originalOptions = $this->fields[$fieldToEdit] ?? [];
        $originalType = $originalOptions['type'] ?? null;
        $originalValues = $originalOptions['values'] ?? [];

        // Collect new options, which might trigger enum generation if type changes to enum
        $newOptions = $this->collectFieldOptions($fieldToEdit, $originalOptions);
        $this->fields[$fieldToEdit] = $newOptions;

        $enumName = Str::studly($this->modelName) . Str::studly($fieldToEdit) . 'Enum';
        $enumPath = app_path("Enums/{$enumName}.php");

        // Case 1: Type changed FROM enum TO something else
        if ($originalType === 'enum' && $newOptions['type'] !== 'enum') {
            if (File::exists($enumPath)) {
                // Optionally ask the user if they want to delete the old enum file
                if ($this->promptService->confirm("Field '{$fieldToEdit}' is no longer an enum. Delete the existing Enum file '{$enumName}.php'?", false)) {
                    File::delete($enumPath);
                    $this->promptService->info("Deleted Enum file: {$enumPath}");
                }
            }
        }
        // Case 2: Type remained enum, but values changed
        elseif ($originalType === 'enum' && $newOptions['type'] === 'enum' && $originalValues !== $newOptions['values']) {
             $this->promptService->info("Enum values changed for '{$fieldToEdit}'. Regenerating Enum file...");
             // Force regeneration by deleting the old one first if it exists
             if (File::exists($enumPath)) {
                 File::delete($enumPath);
             }
             // generateEnumFile is called within collectFieldOptions, but we call it again explicitly
             // after deleting to ensure it's recreated with new values.
             // This assumes collectFieldOptions doesn't skip generation if file exists.
             // Let's refine generateEnumFile to handle overwriting if needed during edits.
             $this->generateEnumFile($fieldToEdit, $newOptions['values']); // Regenerate
        }
        // Case 3: Type changed TO enum (handled inside collectFieldOptions -> generateEnumFile)

        $this->promptService->info("Field '{$fieldToEdit}' has been updated.");
    }

    private function collectFieldOptions(string $fieldName, array $defaults = []): array
    {
        $fieldType = $this->promptService->search(
            "Select field type".( ! empty($defaults) ? " for '{$fieldName}'" : ""),
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

            // Generate Enum file
            $this->generateEnumFile($fieldName, $options['values']);
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

    private function generateEnumFile(string $fieldName, array $values): void
    {
        $enumName = Str::studly($this->modelName) . Str::studly($fieldName) . 'Enum';
        $enumPath = app_path("Enums/{$enumName}.php");
        $enumNamespace = 'App\\Enums';

        if (File::exists($enumPath)) {
            // Don't overwrite if editing and values haven't changed, handled in editField
            // If adding a new field and it exists, maybe prompt? For now, just inform.
            $this->promptService->info("Enum file already exists: {$enumPath}");
            return;
        }

        // Ensure the Enums directory exists
        if (!File::isDirectory(app_path('Enums'))) {
            File::makeDirectory(app_path('Enums'), 0755, true);
        }

        $stubPath = config('rapids.stubs.migration.enum');
        if (!$stubPath || !File::exists($stubPath)) {
             $this->promptService->error("Enum stub file not found at path defined in config: {$stubPath}");
             return;
        }
        $stub = File::get($stubPath);


        $cases = '';
        foreach ($values as $value) {
            // Sanitize value to be a valid case name (e.g., 'draft' -> Draft, 'is_active' -> IsActive)
            $caseName = Str::studly($value);
            // Ensure case name starts with a letter or underscore
             if (!preg_match('/^[a-zA-Z_]/', $caseName)) {
                $caseName = '_' . $caseName; // Prepend underscore if it starts with a number
            }
            // Ensure case name is valid PHP identifier (basic check)
            $caseName = preg_replace('/[^a-zA-Z0-9_\x7f-\xff]/', '', $caseName);

            $cases .= "    case {$caseName} = '{$value}';\n";
        }

        $content = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ cases }}'],
            [$enumNamespace, $enumName, rtrim($cases)], // rtrim to remove trailing newline
            $stub
        );

        File::put($enumPath, $content);
        $this->promptService->info("Created Enum: {$enumPath}");
    }
}
