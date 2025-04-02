<?php

declare(strict_types=1);

namespace Rapids\Rapids\Infrastructure\Laravel;

use Illuminate\Support\Facades\Schema;
use Rapids\Rapids\Contract\ModelInspectorInterface;

class LaravelModelInspector implements ModelInspectorInterface
{
    public function getExistingFields(string $modelName): array
    {
        $modelClass = "App\\Models\\{$modelName}";
        if (!class_exists($modelClass)) {
            return [];
        }

        $instance = new $modelClass();
        return Schema::getColumnListing($instance->getTable());
    }
}
