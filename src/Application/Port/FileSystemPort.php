<?php

declare(strict_types=1);

namespace Rapids\Rapids\Application\Port;

interface FileSystemPort
{
    public function get(string $path): string;

    public function put(string $path, string $content): void;
}
