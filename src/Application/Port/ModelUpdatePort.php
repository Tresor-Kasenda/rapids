<?php

namespace Rapids\Rapids\Application\Port;

interface ModelUpdatePort
{
    public function updateExistingModel(string $modelName): void;
}
