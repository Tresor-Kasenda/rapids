<?php

declare(strict_types=1);

namespace Rapids\Rapids\Infrastructure\Laravel;

use Rapids\Rapids\Contract\PromptServiceInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\success;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

final readonly class LaravelPromptService implements PromptServiceInterface
{
    public function text(string $label, string $placeholder = '', string $default = ''): string
    {
        return text(label: $label, placeholder: $placeholder, default: $default);
    }

    public function select(string $label, array $options, ?string $default = null): string
    {
        return select(label: $label, options: $options, default: $default);
    }

    public function search(string $label, array $options, ?string $default = null): string
    {
        return search(label: $label, options: fn () => $options);
    }

    public function confirm(string $label, bool $default = false): bool
    {
        return confirm(label: $label, default: $default);
    }

    public function table(array $headers, array $data): void
    {
        table($headers, $data);
    }

    public function info(string $message): void
    {
        info($message);
    }

    public function error(string $message): void
    {
        error($message);
    }
    
    public function success(string $message): void
    {
        success($message);
    }
}
