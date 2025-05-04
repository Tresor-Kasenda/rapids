<?php

declare(strict_types=1);

namespace Rapids\Rapids\Domain\Model;

readonly class ModelDefinition
{
    /**
     * @param string $name Nom du modèle
     * @param array $fields Champs du modèle
     * @param array $relations Relations du modèle
     * @param bool $useFillable Utilisation de fillable ou guarded
     * @param bool $useSoftDelete Utilisation de SoftDeletes
     */
    public function __construct(
        private string $name,
        private array $fields = [],
        private array $relations = [],
        private bool $useFillable = true,
        private bool $useSoftDelete = false,
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

    /**
     * @param array $fields
     * @return self
     */
    public function withFields(array $fields): self
    {
        return new self(
            $this->name,
            $fields,
            $this->relations,
            $this->useFillable,
            $this->useSoftDelete
        );
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * @param array $relations
     * @return self
     */
    public function withRelations(array $relations): self
    {
        return new self(
            $this->name,
            $this->fields,
            $relations,
            $this->useFillable,
            $this->useSoftDelete
        );
    }

    public function useFillable(): bool
    {
        return $this->useFillable;
    }

    /**
     * @param bool $useFillable
     * @return self
     */
    public function withUseFillable(bool $useFillable): self
    {
        return new self(
            $this->name,
            $this->fields,
            $this->relations,
            $useFillable,
            $this->useSoftDelete
        );
    }

    public function useSoftDeletes(): bool
    {
        return $this->useSoftDelete;
    }

    /**
     * @param bool $useSoftDelete
     * @return self
     */
    public function withUseSoftDeletes(bool $useSoftDelete): self
    {
        return new self(
            $this->name,
            $this->fields,
            $this->relations,
            $this->useFillable,
            $useSoftDelete
        );
    }
}
