<?php

namespace Rapids\Rapids\Application\Port;

interface FileSystemPort
{
    public function get(string $path): string;

    public function put(string $path, string $content): void;
}
