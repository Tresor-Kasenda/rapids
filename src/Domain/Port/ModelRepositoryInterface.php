<?php

declare(strict_types=1);

namespace Rapids\Rapids\Domain\Port;

interface ModelRepositoryInterface
{
    public function exists(string $modelName): bool;

    public function getInstance(string $modelName): object;
}
