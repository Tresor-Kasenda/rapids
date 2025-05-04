<?php

namespace Rapids\Rapids\Constants;

class LaravelConstants
{
    /**
     * Liste complète de tous les types de relations disponibles dans Laravel
     */
    public const array RELATION_TYPES = [
        'hasOne',
        'belongsTo',
        'hasMany',
        'belongsToMany',
        'hasOneThrough',
        'hasManyThrough',
        'morphOne',
        'morphMany',
        'morphTo',
        'morphToMany',
        'morphedByMany',
    ];

    /**
     * Types de champs disponibles pour la création de modèles
     */
    public const array FIELD_TYPES = [
        'string',
        'text',
        'integer',
        'bigInteger',
        'float',
        'decimal',
        'boolean',
        'date',
        'datetime',
        'timestamp',
        'json',
        'enum',
        'uuid',
        'foreignId',
    ];

    /**
     * Types de contraintes pour les clés étrangères
     */
    public const array FOREIGN_KEY_CONSTRAINTS = [
        'cascade' => 'CASCADE (delete related records)',
        'restrict' => 'RESTRICT (prevent deletion)',
        'nullify' => 'SET NULL (set null on deletion)',
    ];
    
    /**
     * Types de colonnes qui supportent la valeur par défaut
     */
    public const array DEFAULT_VALUE_SUPPORTED_TYPES = [
        'string',
        'text',
        'integer',
        'bigInteger',
        'float',
        'decimal',
        'boolean',
        'enum',
    ];
}
