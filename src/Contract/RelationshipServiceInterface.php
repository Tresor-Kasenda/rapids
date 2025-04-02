<?php

declare(strict_types=1);

namespace Rapids\Rapids\Contract;

interface RelationshipServiceInterface
{
    public function generateRelationMethods(string $modelName, array $relations): string;

    public function generateRelationMethod(string $modelName, string $relationType, string $methodName, string $relatedModel): string;

    public function getRelationMethodName(string $relationType, string $modelName): string;
}
