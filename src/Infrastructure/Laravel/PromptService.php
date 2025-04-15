<?php

declare(strict_types=1);

namespace Rapids\Rapids\Infrastructure\Laravel;

use Rapids\Rapids\Contract\ServiceInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

final class PromptService implements ServiceInterface
{
    public function text(string $label, string $placeholder = '', bool $required = false): string
    {
        return text(
            label: $label,
            placeholder: $placeholder,
            required: $required
        );
    }

    public function confirm(string $label, bool $default = false): bool
    {
        return confirm(label: $label, default: $default);
    }

    public function searchRelationshipType(string $label): string
    {
        return search(
            label: $label,
            options: fn () => [
                'hasOne' => 'Has One',
                'belongsTo' => 'Belongs To',
                'belongsToMany' => 'Belongs To Many',
                'hasMany' => 'Has Many',
                'morphOne' => 'Morph One',
                'morphMany' => 'Morph Many',
                'morphTo' => 'Morph To'
            ],
            placeholder: 'Select relationship type'
        );
    }

    public function searchInverseRelationshipType(string $label): string
    {
        return search(
            label: $label,
            options: fn () => [
                'hasOne' => 'Has One',
                'belongsTo' => 'Belongs To',
                'belongsToMany' => 'Belongs To Many',
                'hasMany' => 'Has Many',
                'morphOne' => 'Morph One',
                'morphMany' => 'Morph Many',
                'morphTo' => 'Morph To',
                'none' => 'No inverse relation'
            ],
            placeholder: 'Select inverse relationship type'
        );
    }

    public function info(string $message): void
    {
        info($message);
    }

    public function select(string $label, array $options): string
    {
        return search(
            label: $label,
            options: fn () => $options,
            placeholder: 'Select an option'
        );
    }
}
