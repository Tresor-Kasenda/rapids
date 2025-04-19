<?php

namespace Rapids\Rapids\Constants;

class LaravelConstants
{
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

    public const array FOREIGN_KEY_CONSTRAINTS = [
        'cascade' => 'CASCADE (delete related records)',
        'restrict' => 'RESTRICT (prevent deletion)',
        'nullify' => 'SET NULL (set null on deletion)',
    ];
}
