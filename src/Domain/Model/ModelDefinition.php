<?php

declare(strict_types=1);

namespace Rapids\Rapids\Domain\Model;

final class ModelDefinition
{
    public function __construct(
        private readonly string $name,
        private array           $fields = [],
        private array           $relations = [],
        private bool            $useFillable = true,
        private bool            $useSoftDelete = false,
    ) {
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

    public function useFillable(): bool
    {
        return $this->useFillable;
    }

    public function useSoftDeletes(): bool
    {
        return $this->useSoftDelete;
    }

    public function setUseSoftDeletes(bool $useSoftDelete): self
    {
        $this->useSoftDelete = $useSoftDelete;
        return $this;
    }
}
