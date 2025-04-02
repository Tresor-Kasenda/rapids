<?php

declare(strict_types=1);

namespace Rapids\Rapids\Contract;

interface ModelInspectorInterface
{
    public function getExistingFields(string $modelName): array;
}
