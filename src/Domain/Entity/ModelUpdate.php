<?php

declare(strict_types=1);

namespace Rapids\Rapids\Domain\Entity;

use Illuminate\Support\Str;

readonly class ModelUpdate
{
    /** @var ModelField[] */
    private array $fields;

    public function __construct(
        private string $modelName,
        array $fields = []
    ) {
        $this->fields = $fields;
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    public function withAddedField(ModelField $field): self
    {
        $newFields = $this->fields;
        $newFields[$field->getName()] = $field;
        return new self($this->modelName, $newFields);
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
