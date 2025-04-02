<?php

namespace Rapids\Rapids\Contract;

interface FileSystemInterface
{
    public function get(string $path): string;

    public function put(string $path, string $content): bool;

    public function exists(string $path): bool;
}
