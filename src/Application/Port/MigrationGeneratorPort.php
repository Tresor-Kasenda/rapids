<?php

declare(strict_types=1);

namespace Rapids\Rapids\Application\Port;

interface MigrationGeneratorPort
{
    public function generateAlterMigration(string $tableName, array $fields): string;
}
