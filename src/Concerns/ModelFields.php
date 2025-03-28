<?php

declare(strict_types=1);

namespace Rapids\Rapids\Concerns;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Rapids\Rapids\Console\RapidCrud;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;

final class ModelFields
{
    private array $relationFields = [];

    public function __construct(
        private readonly RapidCrud $rapidCrud
    ) {
    }

    public function getModelFields(): array
    {
        // Run composer dump-autoload before accessing the model
        if ( ! class_exists("App\\Models\\{$this->rapidCrud->getModelName()}")) {
            $this->rapidCrud->refreshApplication();

            if ( ! class_exists("App\\Models\\{$this->rapidCrud->getModelName()}")) {
                $this->rapidCrud->line("<fg=red>Model {$this->rapidCrud->getModelName()} could not be loaded.</>");
                $this->rapidCrud->line("<fg=yellow>Please select a different model.</>");

                // Ask user to select another model
                $this->rapidCrud->askForModelName();

                // Recursively call getModelFields with the new model
                return $this->getModelFields();
            }
        }

        $model = "App\\Models\\{$this->rapidCrud->getModelName()}";
        $instance = new $model();

        // Get table columns instead of just fillable
        $columns = Schema::getColumnListing($instance->getTable());

        // Store relation fields information
        $this->rapidCrud->setRelationFields([]);

        // Show all fields and let user select them
        $selectedFields = multiselect(
            label: "Select fields to display for {$this->rapidCrud->getModelName()}",
            options: collect($columns)
                ->mapWithKeys(fn ($field) => [$field => Str::title($field)])
                ->all(),
            required: true
        );

        // Handle relations
        foreach ($selectedFields as $field) {
            if (str_ends_with($field, '_id')) {
                $relationName = Str::beforeLast($field, '_id');
                $relationClass = "App\\Models\\".Str::studly($relationName);

                if (class_exists($relationClass)) {
                    $relationInstance = new $relationClass();
                    $relationColumns = Schema::getColumnListing($relationInstance->getTable());

                    $options = collect($relationColumns)
                        ->mapWithKeys(fn ($field) => [$field => Str::title($field)])
                        ->all();

                    info("Selecting display field for {$relationName} relation...");

                    $displayField = search(
                        label: "Select display field for ".Str::title($relationName),
                        options: fn () => $options,
                        placeholder: 'Select a field to display'
                    );

                    $this->relationFields[$field] = $displayField;
                }
            }
        }

        $this->rapidCrud->setSelectedFields($selectedFields);
        return $this->rapidCrud->getSelectedFields();
    }
}
