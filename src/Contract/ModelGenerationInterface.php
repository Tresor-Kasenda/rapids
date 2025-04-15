<?php

declare(strict_types=1);

namespace Rapids\Rapids\Contract;

interface ModelGenerationInterface
{
    public function generateModel(array $fields): void;
}
