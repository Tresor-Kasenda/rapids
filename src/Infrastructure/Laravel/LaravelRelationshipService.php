<?php

declare(strict_types=1);

namespace Rapids\Rapids\Infrastructure\Laravel;

use Illuminate\Support\Str;
use Rapids\Rapids\Contract\RelationshipServiceInterface;

final readonly class LaravelRelationshipService implements RelationshipServiceInterface
{
    public function __construct(private string $modelName = '')
    {
    }

    public function generateRelationMethods(string $modelName, array $relations): string
    {
        $methods = [];

        foreach ($relations as $relation) {
            $relationType = $relation['type'];
            $relatedModel = $relation['model'];

            $methodName = $this->getRelationMethodName($relationType, $relatedModel);
            $methods[] = $this->generateRelationMethod($modelName, $relationType, $methodName, $relatedModel);
        }

        return implode("\n\n    ", $methods);
    }

    public function getRelationMethodName(string $relationType, string $modelName): string
    {
        return match ($relationType) {
            'hasMany', 'belongsToMany', 'morphMany', 'morphToMany', 'morphedByMany', 'hasManyThrough' =>
            Str::camel(Str::plural($modelName)),
            'hasOne', 'belongsTo', 'morphOne', 'morphTo', 'hasOneThrough' =>
            Str::camel(Str::singular($modelName)),
            default => Str::camel($modelName)
        };
    }

    public function generateRelationMethod(string $modelName, string $relationType, string $methodName, string $relatedModel): string
    {
        return "public function {$methodName}()
    {
        return \$this->{$relationType}(\\App\\Models\\{$relatedModel}::class);
    }";
    }
}
