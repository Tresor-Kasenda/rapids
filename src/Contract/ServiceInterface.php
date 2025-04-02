<?php

namespace Rapids\Rapids\Contract;

interface ServiceInterface
{
    public function text(string $label, string $placeholder = '', bool $required = false): string;

    public function confirm(string $label, bool $default = false): bool;

    public function searchRelationshipType(string $label): string;

    public function searchInverseRelationshipType(string $label): string;

    public function info(string $message): void;
}
