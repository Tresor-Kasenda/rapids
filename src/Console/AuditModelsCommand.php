<?php

namespace Rapids\Rapids\Console;

use Illuminate\Console\Command;
use Rapids\Rapids\Application\Port\FileSystemPort;
use Rapids\Rapids\Domain\Entity\ModelField;
use ReflectionClass;
use Throwable;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

class AuditModelsCommand extends Command
{
    protected $signature = 'rapids:audit-models
                                        {--path=app/Models : Path to Laravel models directory}';

    protected $description = 'Audit existing Laravel models and their fields';

    private array $modelInfo = [];

    public function __construct(
        private readonly FileSystemPort $fileSystem
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $modelsPath = $this->option('path');
        info('Auditing models in: ' . $modelsPath);

        $modelFiles = $this->findModelFiles($modelsPath);
        info('Found ' . count($modelFiles) . ' model files');

        foreach ($modelFiles as $modelFile) {
            $this->auditModel($modelFile);
        }

        $this->displayResults();

        return self::SUCCESS;
    }

    private function findModelFiles(string $path): array
    {
        $basePath = base_path($path);
        $files = glob($basePath . '/*.php');

        return array_filter($files, function ($file) {
            $content = $this->fileSystem->get($file);
            return str_contains($content, 'class') &&
                (str_contains($content, 'extends Model') ||
                    str_contains($content, 'Illuminate\Database\Eloquent\Model'));
        });
    }

    private function auditModel(string $filePath): void
    {
        $content = $this->fileSystem->get($filePath);
        $namespace = $this->extractNamespace($content);
        $className = $this->extractClassName($content);
        $fullClassName = $namespace . '\\' . $className;

        try {
            if (!class_exists($fullClassName)) {
                require_once $filePath;
            }

            $model = new $fullClassName();
            $fields = $this->extractModelFields($model);
            $relations = $this->extractModelRelations($model);

            $this->modelInfo[$className] = [
                'fields' => $fields,
                'relations' => $relations
            ];
        } catch (Throwable $e) {
            $this->error("Error auditing model {$className}: {$e->getMessage()}");
        }
    }

    private function extractNamespace(string $content): string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }

    private function extractClassName(string $content): string
    {
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }

    private function extractModelFields($model): array
    {
        $fields = [];

        // Get table schema
        if (method_exists($model, 'getConnection')) {
            $table = $model->getTable();
            $columns = $model->getConnection()->getSchemaBuilder()->getColumnListing($table);

            foreach ($columns as $column) {
                $type = $this->getColumnType($model->getConnection(), $table, $column);

                $fields[] = new ModelField(
                    name: $column,
                    type: $type,
                    isRelation: false
                );
            }
        }

        return $fields;
    }

    private function getColumnType($connection, string $table, string $column): string
    {
        try {
            if (method_exists($connection, 'getDoctrineColumn')) {
                return $connection->getDoctrineColumn($table, $column)
                    ->getType()
                    ->getName();
            }

            // Fallback for SQLite and other connections
            $columns = $connection->select("PRAGMA table_info({$table})");
            foreach ($columns as $col) {
                if ($col->name === $column) {
                    return $this->mapSqliteType($col->type);
                }
            }
            return 'string';
        } catch (Throwable $e) {
            return 'string'; // Default fallback
        }
    }

    private function mapSqliteType(string $sqliteType): string
    {
        return match (strtolower($sqliteType)) {
            'integer', 'int' => 'integer',
            'real', 'float', 'double' => 'float',
            'blob' => 'binary',
            'boolean', 'bool' => 'boolean',
            'timestamp', 'datetime' => 'datetime',
            'date' => 'date',
            'time' => 'time',
            default => 'string',
        };
    }

    private function extractModelRelations($model): array
    {
        $relations = [];
        $reflection = new ReflectionClass($model);
        $methods = $reflection->getMethods();

        $relationTypes = [
            'hasOne', 'hasMany', 'belongsTo', 'belongsToMany',
            'hasManyThrough', 'hasOneThrough', 'morphTo',
            'morphOne', 'morphMany', 'morphToMany'
        ];

        foreach ($methods as $method) {
            if ($method->class != get_class($model)) {
                continue; // Skip inherited methods
            }

            $code = $this->getMethodBody($reflection, $method->name);

            foreach ($relationTypes as $relationType) {
                if (str_contains($code, '$this->' . $relationType)) {
                    // Extract the related model
                    $relatedModel = $this->extractRelatedModel($code);

                    $relations[] = [
                        'name' => $method->name,
                        'type' => $relationType,
                        'related_model' => $relatedModel
                    ];
                    break;
                }
            }
        }

        return $relations;
    }

    private function getMethodBody(ReflectionClass $class, string $methodName): string
    {
        $fileName = $class->getFileName();
        $startLine = $class->getMethod($methodName)->getStartLine();
        $endLine = $class->getMethod($methodName)->getEndLine();

        $content = $this->fileSystem->get($fileName);
        $lines = explode("\n", $content);

        return implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }

    private function extractRelatedModel(string $code): string
    {
        // Pattern to match the class name in relations like return $this->hasMany(Post::class)
        if (preg_match('/\$this->\w+\s*\(\s*([^:,]+)::class/', $code, $matches)) {
            return trim($matches[1]);
        }

        // Pattern for string class names like 'App\Models\Post'
        if (preg_match('/\$this->\w+\s*\(\s*[\'"]([^\'"]+)[\'"]/', $code, $matches)) {
            $parts = explode('\\', $matches[1]);
            return end($parts);
        }

        return 'Unknown';
    }

    private function displayResults(): void
    {
        info('Model Audit Results:');

        $modelsData = [];
        foreach ($this->modelInfo as $modelName => $info) {
            $modelsData[] = [
                'Model' => $modelName,
                'Fields Count' => count($info['fields']),
                'Relations Count' => count($info['relations'])
            ];
        }

        table(['Model', 'Fields Count', 'Relations Count'], $modelsData);

        // Display each model's details
        foreach ($this->modelInfo as $modelName => $info) {
            $this->newLine();
            info("Model: {$modelName}");

            $fieldsData = [];
            foreach ($info['fields'] as $field) {
                $fieldsData[] = [
                    'Name' => $field->getName(),
                    'Type' => $field->getType(),
                    'Is Relation' => $field->isRelation() ? 'Yes' : 'No'
                ];
            }

            info('Fields:');
            table(['Name', 'Type', 'Is Relation'], $fieldsData);

            if (!empty($info['relations'])) {
                $relationsData = [];
                foreach ($info['relations'] as $relation) {
                    $relationsData[] = [
                        'Name' => $relation['name'],
                        'Type' => $relation['type'],
                        'Related To' => $relation['related_model'] ?? 'Unknown'
                    ];
                }

                info('Relations:');
                table(['Name', 'Type', 'Related To'], $relationsData);
            }

            $this->newLine();
            $this->line(str_repeat('-', 50));
        }

        // Display relationship diagram
        $this->displayRelationshipDiagram();
    }

    private function displayRelationshipDiagram(): void
    {
        $this->newLine();
        info('Table Relationships:');

        $relationships = [];

        // Build relationship mapping
        foreach ($this->modelInfo as $modelName => $info) {
            foreach ($info['relations'] as $relation) {
                if (!isset($relation['related_model']) || $relation['related_model'] === 'Unknown') {
                    continue;
                }

                $relationships[] = [
                    'From' => $modelName,
                    'Relation' => $relation['type'],
                    'To' => $relation['related_model'],
                    'Via' => $relation['name']
                ];
            }
        }

        if (empty($relationships)) {
            info('No relationships detected between tables.');
            return;
        }

        table(['From Table', 'Relation Type', 'To Table', 'Via Method'], $relationships);
    }
}
