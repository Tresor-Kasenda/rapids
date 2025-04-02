<?php

namespace Rapids\Rapids\Contract;

interface ModelGenerationInterface
{
    public function generateModel(array $fields): void;
}
