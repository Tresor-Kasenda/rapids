<?php

declare(strict_types=1);

namespace Rapids\Rapids\Infrastructure\Repository;

use Rapids\Rapids\Domain\Port\ModelRepositoryInterface;

final readonly class LaravelModelRepository implements ModelRepositoryInterface
{
    public function exists(string $modelName): bool
    {
        $modelClass = "App\\Models\\{$modelName}";
        return class_exists($modelClass);
    }

    public function getInstance(string $modelName): object
    {
        $modelClass = "App\\Models\\{$modelName}";
        if (!class_exists($modelClass)) {
            throw new \RuntimeException("Model class {$modelClass} not found");
        }
        
        return new $modelClass();
    }
}
