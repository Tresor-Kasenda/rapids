<?php

declare(strict_types=1);

namespace Rapids\Rapids\Application\Port;

interface ModelUpdatePort
{
    public function updateExistingModel(string $modelName): void;
}
