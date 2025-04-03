<?php

namespace Rapids\Rapids\Infrastructure\Repository;

use Illuminate\Support\Facades\File;
use Rapids\Rapids\Domain\Port\ModelRepositoryInterface;
use RuntimeException;
use function Laravel\Prompts\error;

class LaravelModelRepository implements ModelRepositoryInterface
{
    public function getInstance(string $modelName): object
    {
        $modelPath = $this->getModelPath($modelName);

        if (!File::exists($modelPath)) {
            throw new RuntimeException("Model file for {$modelName} does not exist at {$modelPath}");
        }

        $modelClass = "App\\Models\\{$modelName}";

        if (!class_exists($modelClass)) {
            require_once $modelPath;

            if (!class_exists($modelClass)) {
                error("Model class {$modelClass} could not be loaded.");
                throw new RuntimeException("Model class {$modelClass} could not be loaded.");
            }
        }

        return new $modelClass();
    }

    private function getModelPath(string $modelName): string
    {
        return app_path("Models/{$modelName}.php");
    }

    public function exists(string $modelName): bool
    {
        return File::exists($this->getModelPath($modelName));
    }
}
