<?php

namespace Rapids\Rapids\Services;

use Rapids\Rapids\Application\UseCase\GetModelFieldsUseCase;

class ModelFieldsService
{
    private array $selectedFields = [];
    private array $relationFields = [];

    public function __construct(
        private readonly string                $modelName,
        private readonly GetModelFieldsUseCase $modelFieldsUseCase
    )
    {
    }

    public function getModelFields(): array
    {
        $result = $this->modelFieldsUseCase->execute($this->modelName);

        $this->selectedFields = $result['fields'];
        $this->relationFields = $result['relations'];

        return $this->selectedFields;
    }

    public function getSelectedFields(): array
    {
        return $this->selectedFields;
    }

    public function getRelationFields(): array
    {
        return $this->relationFields;
    }
}
