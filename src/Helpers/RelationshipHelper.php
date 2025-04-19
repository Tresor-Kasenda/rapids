<?php

namespace Rapids\Rapids\Helpers;

use Illuminate\Support\Str;

class RelationshipHelper
{
    public static function generateType(string $type, string $model): string
    {
        return match ($type) {
            'hasMany', 'belongsToMany', 'morphMany', 'morphedByMany', 'hasManyThrough' => Str::camel(Str::plural($model)),
            default => Str::camel(Str::singular($model)),
        };
    }

    public static function generateMethod(string $type, string $methodName, string $model): string
    {
        return match ($type) {
            'hasOne' => "return \$this->hasOne({$model}::class);",
            'hasMany' => "return \$this->hasMany({$model}::class);",
            'belongsTo' => "return \$this->belongsTo({$model}::class);",
            default => "// Method logic for {$type}",
        };
    }
}
