<?php

declare(strict_types=1);

namespace Rapids\Rapids\Application\UseCase;

use Rapids\Rapids\Application\Port\FileSystemPort;
use Rapids\Rapids\Application\Port\MigrationGeneratorPort;
use Rapids\Rapids\Application\Port\UserInterfacePort;
use Rapids\Rapids\Domain\Entity\ModelUpdate;

final readonly class UpdateExistingModelUseCase
{
    public function __construct(
        private FileSystemPort         $fileSystem,
        private MigrationGeneratorPort $migrationGenerator,
        private UserInterfacePort      $userInterface
    ) {
    }

    public function execute(ModelUpdate $modelUpdate): void
    {
        $tableName = $modelUpdate->getTableName();
        $fields = $modelUpdate->getFields();

        $migrationContent = $this->migrationGenerator->generateAlterMigration(
            $tableName,
            $fields
        );

        $migrationName = 'add_fields_to_'.$tableName.'_table';
        $migrationFile = $this->getMigrationPath($migrationName);
        $this->fileSystem->put($migrationFile, $migrationContent);

        $this->userInterface->info('Migration created successfully.');
    }

    private function getMigrationPath(string $migrationName): string
    {
        return database_path("migrations/".date('Y_m_d_His_').$migrationName.'.php');
    }
}
