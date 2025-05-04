<?php

declare(strict_types=1);

namespace Rapids\Rapids\Infrastructure\Adapter;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Rapids\Rapids\Application\Port\FileSystemPort;

final readonly class FileSystemAdapter implements FileSystemPort
{
    public function __construct(
        private Filesystem $filesystem
    ) {
    }

    /**
     * @throws FileNotFoundException
     */
    public function get(string $path): string
    {
        return $this->filesystem->get($path);
    }

    public function put(string $path, string $content): void
    {
        $this->filesystem->put($path, $content);
    }
}
