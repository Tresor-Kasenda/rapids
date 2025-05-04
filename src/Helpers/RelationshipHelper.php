<?php

declare(strict_types=1);

namespace Rapids\Rapids\Helpers;

use Illuminate\Support\Str;
use Rapids\Rapids\Constants\LaravelConstants;

/**
 * Helper pour la gestion des relations Laravel
 */
final readonly class RelationshipHelper
{
    /**
     * Génère un type basé sur le type de relation et le modèle
     * 
     * @param string $type Type de relation
     * @param string $model Nom du modèle
     * @return string Type généré
     */
    public static function generateType(string $type, string $model): string
    {
        return match ($type) {
            'hasMany', 'belongsToMany', 'morphMany', 'morphedByMany', 'hasManyThrough' => Str::camel(Str::plural($model)),
            default => Str::camel(Str::singular($model)),
        };
    }

    /**
     * Génère le code d'une méthode de relation
     * 
     * @param string $type Type de relation
     * @param string $methodName Nom de la méthode
     * @param string $model Nom du modèle
     * @return string Code PHP généré
     */
    public static function generateMethod(string $type, string $methodName, string $model): string
    {
        // Vérifier si le type de relation est valide
        if (!in_array($type, LaravelConstants::RELATION_TYPES)) {
            return "// Type de relation inconnu: {$type}";
        }

        return match ($type) {
            'hasOne' => "return \$this->hasOne({$model}::class);",
            'hasMany' => "return \$this->hasMany({$model}::class);",
            'belongsTo' => "return \$this->belongsTo({$model}::class);",
            'belongsToMany' => "return \$this->belongsToMany({$model}::class);",
            'hasOneThrough' => "return \$this->hasOneThrough({$model}::class, IntermediateModel::class);",
            'hasManyThrough' => "return \$this->hasManyThrough({$model}::class, IntermediateModel::class);",
            'morphOne' => "return \$this->morphOne({$model}::class, 'morphable');",
            'morphMany' => "return \$this->morphMany({$model}::class, 'morphable');",
            'morphTo' => "return \$this->morphTo();",
            'morphToMany' => "return \$this->morphToMany({$model}::class, 'taggable');",
            'morphedByMany' => "return \$this->morphedByMany({$model}::class, 'taggable');",
            default => "// Logique pour {$type} à implémenter",
        };
    }

    /**
     * Génère le code d'une méthode de relation avec des indications pour les types complexes
     * 
     * @param string $type Type de relation
     * @param string $methodName Nom de la méthode
     * @param string $model Nom du modèle
     * @return string Message explicatif et code PHP généré
     */
    public static function generateMethodWithHint(string $type, string $methodName, string $model): string
    {
        $methodCode = self::generateMethod($type, $methodName, $model);
        
        $hint = match ($type) {
            'hasOneThrough', 'hasManyThrough' => 
                "// Remplacez IntermediateModel par le modèle intermédiaire approprié\n    ",
            'morphOne', 'morphMany' => 
                "// Remplacez 'morphable' par le nom polymorphique approprié\n    ",
            'morphToMany', 'morphedByMany' => 
                "// Remplacez 'taggable' par le nom polymorphique approprié\n    ",
            default => "",
        };
        
        return $hint . $methodCode;
    }

    /**
     * Détermine si un type de relation nécessite des champs polymorphiques
     * 
     * @param string $type Type de relation
     * @return bool True si la relation est polymorphique
     */
    public static function isPolymorphicRelation(string $type): bool
    {
        return in_array($type, ['morphOne', 'morphMany', 'morphTo', 'morphToMany', 'morphedByMany']);
    }

    /**
     * Détermine les champs requis pour un type de relation
     * 
     * @param string $type Type de relation
     * @param string $modelName Nom du modèle
     * @return array Liste des champs requis
     */
    public static function getRequiredFields(string $type, string $modelName): array
    {
        $singularModel = Str::singular(Str::snake($modelName));
        
        return match ($type) {
            'belongsTo' => ["{$singularModel}_id"],
            'morphTo' => [
                "{$singularModel}able_id", 
                "{$singularModel}able_type"
            ],
            default => [],
        };
    }
}
