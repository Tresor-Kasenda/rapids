<?php

declare(strict_types=1);

namespace Rapids\Rapids\Domain\Model;

class ModelDefinition
{
    public function __construct(
        private string $name,
        private array  $fields = [],
        private array  $relations = []
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function addField(string $name, array $options): self
    {
        $this->fields[$name] = $options;
        return $this;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function addRelation(array $relation): self
    {
        $this->relations[] = $relation;
        return $this;
    }
}
