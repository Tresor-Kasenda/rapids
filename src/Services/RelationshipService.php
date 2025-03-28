<?php

declare(strict_types=1);

namespace Rapids\Rapids\Services;

final class RelationshipService
{
    public function generateRelationMethod(string $type, string $methodName, string $relatedModel): string
    {
        return match ($type) {
            'belongsTo' => $this->generateBelongsToMethod($methodName, $relatedModel),
            'hasOne' => $this->generateHasOneMethod($methodName, $relatedModel),
            'hasMany' => $this->generateHasManyMethod($methodName, $relatedModel),
            'belongsToMany' => $this->generateBelongsToManyMethod($methodName, $relatedModel),
            'hasOneThrough' => $this->generateHasOneThroughMethod($methodName, $relatedModel),
            'hasManyThrough' => $this->generateHasManyThroughMethod($methodName, $relatedModel),
            'morphOne' => $this->generateMorphOneMethod($methodName, $relatedModel),
            'morphMany' => $this->generateMorphManyMethod($methodName, $relatedModel),
            'morphTo' => $this->generateMorphToMethod($methodName),
            'morphToMany' => $this->generateMorphToManyMethod($methodName, $relatedModel),
            'morphedByMany' => $this->generateMorphedByManyMethod($methodName, $relatedModel),
            default => ''
        };
    }
}
