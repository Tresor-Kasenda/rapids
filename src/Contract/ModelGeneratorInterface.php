<?php

declare(strict_types=1);

namespace Rapids\Rapids\Contract;

use Rapids\Rapids\Domain\Model\ModelDefinition;

interface ModelGeneratorInterface
{
    public function generateModel(ModelDefinition $modelDefinition): void;
}
