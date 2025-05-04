<?php

declare(strict_types=1);

namespace Rapids\Rapids\Domain\Entity;

readonly class ModelField
{
    public function __construct(
        private string $name,
        private string $type,
        private bool $isRelation
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isRelation(): bool
    {
        return $this->isRelation;
    }
}
