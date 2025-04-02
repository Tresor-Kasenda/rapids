<?php

declare(strict_types=1);

namespace Rapids\Rapids\Contract;

interface PromptServiceInterface
{
    public function text(string $label, string $placeholder = '', string $default = ''): string;

    public function select(string $label, array $options, ?string $default = null): string;

    public function search(string $label, array $options, ?string $default = null): string;

    public function confirm(string $label, bool $default = false): bool;

    public function table(array $headers, array $data): void;

    public function info(string $message): void;

    public function error(string $message): void;

    
}
