<?php

declare(strict_types=1);

namespace Rapids\Rapids\Infrastructure\Laravel;

use Illuminate\Support\Facades\File;
use Rapids\Rapids\Contract\FileSystemInterface;

class LaravelFileSystem implements FileSystemInterface
{
    public function get(string $path): string
    {
        return File::get($path);
    }
    
    public function put(string $path, string $content): bool
    {
        return File::put($path, $content) !== false;
    }

    public function exists(string $path): bool
    {
        return File::exists($path);
    }
}
