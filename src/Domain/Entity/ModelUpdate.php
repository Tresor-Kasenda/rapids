<?php

declare(strict_types=1);

namespace Rapids\Rapids\Domain\Entity;

use Illuminate\Support\Str;

final class ModelUpdate
{
    /** @var ModelField[] */
    private array $fields = [];

    public function __construct(
        private readonly string $modelName
    ) {
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    public function addField(ModelField $field): self
    {
        $this->fields[$field->getName()] = $field;
        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getTableName(): string
    {
        return Str::snake(Str::pluralStudly($this->modelName));
    }
}
