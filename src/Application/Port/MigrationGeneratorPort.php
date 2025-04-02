<?php

namespace Rapids\Rapids\Application\Port;

interface MigrationGeneratorPort
{
    public function generateAlterMigration(string $tableName, array $fields): string;
}
