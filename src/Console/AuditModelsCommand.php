<?php

declare(strict_types=1);

namespace Rapids\Rapids\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Rapids\Rapids\Application\Port\FileSystemPort;
use Rapids\Rapids\Domain\Entity\ModelField;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Throwable;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

final class AuditModelsCommand extends Command
{
    protected $signature = 'rapids:audit-insight
                            {--path=app/Models : Path to Laravel models directory or specific model file}
                            {--output=docs/database-schema.md : Output file path for documentation}
                            {--ignore-factories : Exclude factory methods from relationship analysis}';

    protected $description = 'Audit existing Laravel models and their fields';

    private array $modelInfo = [];

    public function __construct(
        private readonly FileSystemPort $fileSystem
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $pathOption = $this->option('path');
        $isDefaultPath = 'app/Models' === $pathOption;

        if ($this->isSingleModelPath($pathOption)) {
            $modelPath = $this->resolveModelPath($pathOption);
            if ($modelPath) {
                info('Performing detailed audit on model: '.$modelPath);
                $this->auditSingleModelDetailed($modelPath);
                return self::SUCCESS;
            } else {
                $this->error("Model not found at path: {$pathOption}");
                return self::FAILURE;
            }
        }

        if ($isDefaultPath) {
            info('Auditing all models in the project...');
            $modelFiles = $this->findAllProjectModels();
        } else {
            info('Auditing models in: '.$pathOption);
            $modelFiles = $this->findModelFiles($pathOption);
        }

        info('Found '.count($modelFiles).' model files');

        $this->batchProcessModels($modelFiles);

        if (defined('STDOUT') && ! posix_isatty(STDOUT)) {
            $outputPath = $this->option('output') ?? 'docs/database-schema.md';
            $documentation = $this->generateDocumentationOutput($outputPath);
            echo $documentation;
        } else {
            $this->displayResults();
        }

        return self::SUCCESS;
    }

    private function isSingleModelPath(string $path): bool
    {
        // Check if path is for a single model file (either full path or just model name)
        return str_ends_with($path, '.php') ||
            ! str_contains($path, '/') ||
            file_exists(base_path("app/Models/{$path}.php"));
    }

    private function resolveModelPath(string $path): ?string
    {
        if (str_ends_with($path, '.php') && file_exists(base_path($path))) {
            return base_path($path);
        }

        if ( ! str_contains($path, '/') && file_exists(base_path("app/Models/{$path}.php"))) {
            return base_path("app/Models/{$path}.php");
        }

        if (file_exists(base_path($path.'.php'))) {
            return base_path($path.'.php');
        }

        return null;
    }

    private function auditSingleModelDetailed(string $filePath): void
    {
        $content = $this->fileSystem->get($filePath);
        $namespace = $this->extractNamespace($content);
        $className = $this->extractClassName($content);
        $fullClassName = $namespace.'\\'.$className;

        try {
            if ( ! class_exists($fullClassName)) {
                require_once $filePath;
            }

            // Check if class actually extends Model
            if ( ! is_subclass_of($fullClassName, 'Illuminate\Database\Eloquent\Model')) {
                warning("Skipping {$className}: Not an Eloquent Model");
                return;
            }

            $model = new $fullClassName();

            // Basic model information
            $this->info("Detailed Audit for Model: {$className}");
            $this->line(str_repeat('=', 80));

            // Extract fields and relations for statistics
            $fields = $this->extractModelFields($model);
            $relations = $this->extractModelRelations($model);
            $tableName = $model->getTable();
            $primaryKey = $model->getKeyName();
            $usesSoftDeletes = in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($fullClassName));
            $recordCount = 0;

            try {
                $recordCount = DB::table($tableName)->count();
            } catch (Throwable $e) {
                // Silently handle count failures
            }

            // MODEL OVERVIEW section
            $this->info(" MODEL OVERVIEW:");
            $overviewData = [
                ['Property', 'Value'],
                ['Name', $className],
                ['Namespace', $namespace],
                ['Table', $tableName],
                ['Primary Key', $primaryKey],
                ['Fields Count', count($fields)],
                ['Relations Count', count($relations)],
                ['Created At', $this->getFileStats($filePath)['created']],
                ['Last Modified', $this->getFileStats($filePath)['modified']],
                ['File Size', $this->getFileStats($filePath)['size']],
            ];
            $this->table([], $overviewData);

            $this->newLine();

            // File information
            $this->info("📁 FILE INFORMATION:");
            $this->line("Path: {$filePath}");

            $fileStats = $this->getFileStats($filePath);
            $this->line("Created: {$fileStats['created']}");
            $this->line("Last Modified: {$fileStats['modified']}");
            $this->line("File Size: {$fileStats['size']}");

            $this->newLine();

            // Model properties
            $this->info("📊 MODEL PROPERTIES:");
            $this->line("Table Name: {$tableName}");
            $this->line("Primary Key: {$primaryKey}");
            $this->line("Uses Timestamps: ".($model->usesTimestamps() ? 'Yes' : 'No'));
            $this->line("Uses Soft Deletes: ".($usesSoftDeletes ? 'Yes' : 'No'));

            $this->newLine();

            // Fields analysis
            $this->info("🔤 FIELDS ANALYSIS (".count($fields)." fields):");

            $fieldsData = [];
            foreach ($fields as $field) {
                $fieldsData[] = [
                    'Name' => $field->getName(),
                    'Type' => $field->getType(),
                    'Is Relation' => $field->isRelation() ? 'Yes' : 'No'
                ];
            }
            table(['Name', 'Type', 'Is Relation'], $fieldsData);

            $this->newLine();

            // Relations analysis
            $this->info("🔗 RELATIONS ANALYSIS (".count($relations)." relations):");

            if (empty($relations)) {
                $this->line("No relations defined in this model.");
            } else {
                $relationsData = [];
                foreach ($relations as $relation) {
                    $relationsData[] = [
                        'Name' => $relation['name'],
                        'Type' => $relation['type'],
                        'Related To' => $relation['related_model'] ?? 'Unknown'
                    ];
                }
                table(['Name', 'Type', 'Related To'], $relationsData);
            }

            $this->newLine();

            // MODEL USAGE STATISTICS
            $this->info("📊 MODEL USAGE STATISTICS:");
            $referencingModels = $this->findModelsReferencingThis($className);
            $this->line("Referenced By Models: ".count($referencingModels));
            $dbSize = $this->estimateDatabaseSize($tableName);
            try {
                $usageStatsData = [
                    ['Statistic', 'Value'],
                    ['Records Count', $recordCount],
                    ['Referenced By Models', count($referencingModels)],
                    ['Database Size (MB)', $dbSize]
                ];

                // Add timestamp information if available
                if ($recordCount > 0) {
                    try {
                        if ($model->usesTimestamps()) {
                            $latestDate = DB::table($tableName)->max('created_at');
                            $oldestDate = DB::table($tableName)->min('created_at');

                            if ($latestDate) {
                                $usageStatsData[] = ['Most Recent Record', $latestDate];
                            }

                            if ($oldestDate) {
                                $usageStatsData[] = ['Oldest Record', $oldestDate];
                            }
                        }
                    } catch (Throwable $e) {
                        // Skip timestamp info if not applicable
                    }
                }

                // Display the table
                $this->table([], $usageStatsData);

            } catch (Throwable $e) {
                warning("Could not retrieve usage statistics: ".$e->getMessage());
            }

            $this->newLine();

            // Database statistics
            $this->info("💾 DATABASE STATISTICS:");
            try {
                $this->line("Total Records: {$recordCount}");

                if ($recordCount > 0) {
                    // Get latest records
                    $latestRecords = DB::table($tableName)->orderBy($usesSoftDeletes ? 'updated_at' : $primaryKey, 'desc')->limit(3)->get();
                    $this->line("Latest Records (up to 3, excluding timestamps and null fields):");
                    $this->displaySampleData($latestRecords, ['created_at', 'updated_at', 'deleted_at']);
                }
            } catch (Throwable $e) {
                warning("Could not retrieve database statistics: ".$e->getMessage());
            }

            $this->newLine();

            // File History (Git history)
            $this->info("📜 FILE HISTORY:");
            $gitHistory = $this->getGitHistory($filePath);
            if ( ! empty($gitHistory)) {
                table(['Date', 'Author', 'Commit Message'], $gitHistory);
            } else {
                $this->line("No git history available or git not installed.");
            }

            $this->newLine();

            // DEPENDENCIES GRAPH
            $this->info("🔄 DEPENDENCIES GRAPH:");
            $referencingFiles = $this->findReferencingFiles($className);
            $this->displayDependenciesGraph($className, $relations, $referencingFiles);
            $this->newLine();

            // PERFORMANCE INSIGHTS
            $this->info("⚡ PERFORMANCE INSIGHTS:");
            $this->displayPerformanceInsights($model, $fields, $relations, $recordCount);

            $this->info("📊 CODE COMPLEXITY METRICS:");
            $complexityMetrics = $this->analyzeCodeComplexity($content);
            table([
                'Metric', 'Value', 'Recommendation'
            ], [
                ['Methods Count', $complexityMetrics['methods'], $complexityMetrics['methods'] > 15 ? 'Consider splitting class' : 'OK'],
                ['Average Method Length', $complexityMetrics['avgMethodLength'], $complexityMetrics['avgMethodLength'] > 20 ? 'Methods may be too long' : 'OK'],
                ['Relation Count', count($relations), count($relations) > 10 ? 'High coupling detected' : 'OK']
            ]);


            // Source code structure analysis
            $this->analyzeSourceCode($content, $fullClassName);

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
        return match (mb_strtolower($sqliteType)) {
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

        // Factory method patterns to exclude
        $factoryPatterns = [
            '/factory$/i',
            '/factory[A-Z]/i',
            '/make[A-Z].*Factory/i',
            '/create[A-Z].*Factory/i',
            '/new[A-Z].*Factory/i'
        ];

        $relationTypes = [
            'hasOne', 'hasMany', 'belongsTo', 'belongsToMany',
            'hasManyThrough', 'hasOneThrough', 'morphTo',
            'morphOne', 'morphMany', 'morphToMany'
        ];

        foreach ($methods as $method) {
            // Skip inherited and factory-related methods
            if ($method->class !== get_class($model) || $this->isFactoryMethod($method->name, $factoryPatterns)) {
                continue;
            }

            $code = $this->getMethodBody($reflection, $method->name);

            // Skip factory-related code
            if ($this->containsFactoryCode($code)) {
                continue;
            }

            foreach ($relationTypes as $relationType) {
                if (str_contains($code, '$this->'.$relationType)) {
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

    private function isFactoryMethod(string $methodName, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $methodName)) {
                return true;
            }
        }
        return false;
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

    private function containsFactoryCode(string $code): bool
    {
        $factoryPatterns = [
            '/factory\s*\(/i',
            '/Factory::/i',
            '/->factory\(\)/i',
            '/make[A-Z][a-zA-Z]*Factory/i',
            '/create[A-Z][a-zA-Z]*Factory/i'
        ];

        foreach ($factoryPatterns as $pattern) {
            if (preg_match($pattern, $code)) {
                return true;
            }
        }

        return false;
    }

    private function extractRelatedModel(string $code): string
    {
        // Pattern to match the class name in relations like return $this->hasMany(Post::class)
        if (preg_match('/\$this->\w+\s*\(\s*([^:,]+)::class/', $code, $matches)) {
            return mb_trim($matches[1]);
        }

        // Pattern for string class names like 'App\Models\Post'
        if (preg_match('/\$this->\w+\s*\(\s*[\'"]([^\'"]+)[\'"]/', $code, $matches)) {
            $parts = explode('\\', $matches[1]);
            return end($parts);
        }

        return 'Unknown';
    }

    private function getFileStats(string $filePath): array
    {
        $stats = [];

        if (file_exists($filePath)) {
            $stats['created'] = date("Y-m-d H:i:s", filectime($filePath));
            $stats['modified'] = date("Y-m-d H:i:s", filemtime($filePath));
            $stats['size'] = $this->formatBytes(filesize($filePath));
        } else {
            $stats['created'] = 'Unknown';
            $stats['modified'] = 'Unknown';
            $stats['size'] = 'Unknown';
        }

        return $stats;
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }

    private function findModelsReferencingThis(string $modelName): array
    {
        $referencingModels = [];

        foreach ($this->modelInfo as $refModelName => $info) {
            foreach ($info['relations'] ?? [] as $relation) {
                if (($relation['related_model'] ?? '') === $modelName) {
                    $referencingModels[] = $refModelName;
                }
            }
        }

        return $referencingModels;
    }

    private function estimateDatabaseSize(string $tableName): string
    {
        try {
            // Try to get actual size from database
            $size = 0;

            $connection = DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ('mysql' === $connection) {
                $result = DB::select("
                    SELECT
                        round(((data_length + index_length) / 1024 / 1024), 2) as 'size_mb'
                    FROM information_schema.TABLES
                    WHERE table_schema = DATABASE()
                    AND table_name = ?
                ", [$tableName]);

                if ( ! empty($result)) {
                    $size = $result[0]->size_mb;
                }
            }

            return number_format($size, 2);
        } catch (Throwable $e) {
            return 'Unknown';
        }
    }

    private function displaySampleData($records, array $excludeFields = []): void
    {
        if (0 === count($records)) {
            $this->line("No records found.");
            return;
        }

        // Get all columns, excluding the specified ones
        $allColumns = array_keys((array)$records[0]);
        $columns = array_diff($allColumns, $excludeFields);

        // Prepare data rows
        $data = [];
        $nonNullColumns = [];

        foreach ($records as $record) {
            $row = [];
            $recordArray = (array)$record;

            foreach ($columns as $key) {
                $value = $recordArray[$key] ?? null;

                // Skip null values
                if (null === $value) {
                    continue;
                }

                // Truncate long values
                if (is_string($value) && mb_strlen($value) > 30) {
                    $value = mb_substr($value, 0, 27).'...';
                }

                $row[$key] = $value;
                $nonNullColumns[$key] = true;
            }

            $data[] = $row;
        }

        // Filter columns to only those with non-null values
        $finalColumns = array_keys($nonNullColumns);

        // Rebuild data with consistent columns
        $finalData = [];
        foreach ($data as $row) {
            $newRow = [];
            foreach ($finalColumns as $col) {
                $newRow[$col] = $row[$col] ?? 'NULL';
            }
            $finalData[] = $newRow;
        }

        if (empty($finalColumns)) {
            $this->line("No non-null data to display after filtering.");
            return;
        }

        table($finalColumns, $finalData);
    }

    private function getGitHistory(string $filePath): array
    {
        try {
            $relativePath = str_replace(base_path().'/', '', $filePath);
            $command = "git log -5 --pretty=format:'%ad|%an|%s' --date=short -- ".escapeshellarg($relativePath);
            $output = shell_exec($command);

            if ( ! $output) {
                return [];
            }

            $history = [];
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                $parts = explode('|', $line);
                if (3 === count($parts)) {
                    $history[] = [
                        'Date' => $parts[0],
                        'Author' => $parts[1],
                        'Commit Message' => $parts[2]
                    ];
                }
            }

            return $history;
        } catch (Throwable) {
            return [];
        }
    }

    private function findReferencingFiles(string $modelName): array
    {
        $files = [];
        $directories = [
            'app/Http/Controllers',
            'app/Services',
            'app/Repositories',
            'app/Actions',
            'app/Traits',
            'app/Observers'
        ];

        foreach ($directories as $directory) {
            $path = base_path($directory);
            if ( ! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && 'php' === $file->getExtension()) {
                    $content = file_get_contents($file->getRealPath());
                    if (preg_match('/\b'.preg_quote($modelName, '/').'\b/', $content)) {
                        $files[] = $file->getRealPath();
                    }
                }
            }
        }

        return $files;
    }

    private function displayDependenciesGraph(string $modelName, array $relations, array $referencingFiles): void
    {
        if (defined('STDOUT') && ! posix_isatty(STDOUT)) {
            $this->displayMermaidGraph($modelName, $relations, $referencingFiles);
        } else {
            $this->displayAsciiGraph($modelName, $relations, $referencingFiles);
        }
    }

    private function displayMermaidGraph(string $modelName, array $relations, array $referencingFiles): void
    {
        $this->line('```mermaid');
        $this->line('graph TD;');
        $this->line('classDef main fill:#f96,stroke:#333,stroke-width:2px;');
        $this->line('classDef required fill:#9cf,stroke:#333,stroke-width:1px;');
        $this->line('classDef requiredBy fill:#fcf,stroke:#333,stroke-width:1px;');

        $mainNode = $this->sanitizeNodeId($modelName);
        $this->line("    {$mainNode}[\"{$modelName}\"]:::main;");

        foreach ($referencingFiles as $file) {
            $nodeId = $this->sanitizeNodeId(basename($file));
            $label = basename($file).'<br/>('.$this->getRelativePath($file).')';
            $this->line("    {$nodeId}[\"{$label}\"]:::requiredBy;");
            $this->line("    {$nodeId} -->|uses| {$mainNode};");
        }

        $requiredModels = $this->getRequiredModels($relations);
        foreach ($requiredModels as $model) {
            $nodeId = $this->sanitizeNodeId($model);
            $path = $this->findModelPath($model);
            $label = $model.'<br/>('.$this->getRelativePath($path).')';
            $this->line("    {$nodeId}[\"{$label}\"]:::required;");
            $this->line("    {$mainNode} -->|requires| {$nodeId};");
        }

        $this->line('```');
    }

    private function sanitizeNodeId(string $name): string
    {
        // Remove special characters and spaces, ensure valid Mermaid node ID
        return preg_replace('/[^a-zA-Z0-9]/', '_', $name);
    }

    private function getRelativePath(string $path): string
    {
        $basePath = base_path();
        if (str_starts_with($path, $basePath)) {
            return mb_substr($path, mb_strlen($basePath) + 1);
        }
        return $path;
    }

    private function getRequiredModels(array $relations): array
    {
        $models = [];
        foreach ($relations as $relation) {
            if ( ! empty($relation['related_model']) && 'Unknown' !== $relation['related_model']) {
                $models[] = $relation['related_model'];
            }
        }
        return array_unique($models);
    }

    private function findModelPath(string $modelName): string
    {
        $commonPaths = [
            'app/Models/',
            'app/Domain/Models/',
            'app/'
        ];

        foreach ($commonPaths as $path) {
            $fullPath = base_path($path.$modelName.'.php');
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        return 'app/Models/'.$modelName.'.php';
    }

    private function displayAsciiGraph(string $modelName, array $relations, array $referencingFiles): void
    {
        $this->line("\nDependencies Graph:");
        $this->line('┌'.str_repeat('─', mb_strlen($modelName) + 2).'┐');
        $this->line('│ '.$modelName.' │');
        $this->line('└'.str_repeat('─', mb_strlen($modelName) + 2).'┘');

        if ( ! empty($referencingFiles)) {
            $this->line("\nRequired by:");
            foreach ($referencingFiles as $index => $file) {
                $isLast = $index === count($referencingFiles) - 1;
                $prefix = $isLast ? '└──' : '├──';
                $fileName = basename($file);
                $path = $this->getRelativePath($file);
                $this->line(sprintf("%s %s (%s)", $prefix, $fileName, $path));
            }
        }

        $requiredModels = $this->getRequiredModels($relations);
        if ( ! empty($requiredModels)) {
            $this->line("\nRequires:");
            foreach ($requiredModels as $index => $model) {
                $isLast = $index === count($requiredModels) - 1;
                $prefix = $isLast ? '└──' : '├──';
                $path = $this->getRelativePath($this->findModelPath($model));
                $this->line(sprintf("%s %s (%s)", $prefix, $model, $path));
            }
        }

        if (empty($referencingFiles) && empty($requiredModels)) {
            $this->line("\nNo dependencies found.");
        }
    }

    private function displayPerformanceInsights($model, array $fields, array $relations, int $recordCount): void
    {
        $insights = [];
        $insightsData = [['Type', 'Description']];

        // Check for potential missing indexes
        $hasIndexableFields = false;
        foreach ($fields as $field) {
            if (str_contains(mb_strtolower($field->getName()), 'id') && ! str_contains(mb_strtolower($field->getName()), 'primary')) {
                $hasIndexableFields = true;
                $insightsData[] = [
                    'Missing Indexes',
                    "Consider adding indexes on foreign key '{$field->getName()}'"
                ];
                break;
            }
        }

        // Check for many relationships that might benefit from eager loading
        if (count($relations) > 3) {
            $insightsData[] = [
                'Frequent Joins',
                'Consider eager loading relationships to reduce query count'
            ];
        }

        // For large record count, suggest pagination
        if ($recordCount > 10000) {
            $insightsData[] = [
                'Large Record Count',
                "Consider implementing pagination for this model's collections"
            ];
        }

        // Check for potential missing foreign keys
        $potentialFks = [];
        foreach ($relations as $relation) {
            if ('belongsTo' === $relation['type'] && ! empty($relation['related_model'])) {
                $potentialFks[] = $relation['name'];
            }
        }

        if ( ! empty($potentialFks)) {
            $insightsData[] = [
                'Missing Foreign Keys',
                'Ensure foreign keys exist for: '.implode(", ", $potentialFks)
            ];
        }

        if (1 === count($insightsData)) {
            $this->line("No specific performance insights detected.");
        } else {
            table([], $insightsData);
        }
    }

    private function analyzeCodeComplexity(string $content): array
    {
        return [
            'methods' => mb_substr_count($content, 'function'),
            'avgMethodLength' => $this->calculateAverageMethodLength($content),
            'complexity' => $this->calculateCyclomaticComplexity($content)
        ];
    }

    private function calculateAverageMethodLength(string $content): int
    {
        preg_match_all('/function\s+\w+\([^)]*\)\s*{([^}]*)}/', $content, $matches);
        if (empty($matches[1])) {
            return 0;
        }

        $totalLines = 0;
        foreach ($matches[1] as $methodBody) {
            $totalLines += mb_substr_count($methodBody, "\n") + 1;
        }

        return (int)($totalLines / count($matches[1]));
    }

    private function calculateCyclomaticComplexity(string $content): int
    {
        // Count decision points that increase complexity
        $complexity = 0;

        // Count control structures
        $complexity += mb_substr_count($content, 'if');
        $complexity += mb_substr_count($content, 'else');
        $complexity += mb_substr_count($content, 'case');
        $complexity += mb_substr_count($content, 'for');
        $complexity += mb_substr_count($content, 'foreach');
        $complexity += mb_substr_count($content, 'while');
        $complexity += mb_substr_count($content, 'do');
        $complexity += mb_substr_count($content, '&&');
        $complexity += mb_substr_count($content, '||');
        $complexity += mb_substr_count($content, '?');

        // Base complexity is 1
        return $complexity + 1;
    }

    private function analyzeSourceCode(string $content, string $fullClassName): void
    {
        $this->info("📋 SOURCE CODE STRUCTURE ANALYSIS:");

        // Count of properties/attributes
        preg_match_all('/(?:private|protected|public)\s+(?:\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/m', $content, $propertiesMatches);
        $propertiesCount = count($propertiesMatches[0] ?? []);

        // Find traits and interfaces
        preg_match('/class\s+\w+(?:\s+extends\s+\w+)?(?:\s+implements\s+([\w\s,]+))?/m', $content, $implementsMatch);
        preg_match_all('/use\s+([\w\\\\]+)(\s+as\s+[\w]+)?;/m', $content, $useMatches);
        $interfaces = isset($implementsMatch[1]) ? array_map('trim', explode(',', $implementsMatch[1])) : [];
        $traits = array_filter($useMatches[1] ?? [], fn ($use) => str_contains($use, 'Trait') || class_exists($use) && (new ReflectionClass($use))->isTrait());

        // Documentation coverage
        preg_match_all('!/\*\*.*?\*/!s', $content, $docblockMatches);
        $docCount = count($docblockMatches[0] ?? []);

        // Count lines of code
        $lineCount = mb_substr_count($content, "\n") + 1;

        // Display metrics
        table([
            'Metric', 'Value', 'Notes'
        ], [
            ['Lines of Code', $lineCount, $lineCount > 300 ? 'Consider splitting class' : 'OK'],
            ['Properties', $propertiesCount, ''],
            ['Traits Used', count($traits), implode(', ', array_map(fn ($trait) => basename(str_replace('\\', '/', $trait)), $traits))],
            ['Implements', count($interfaces), implode(', ', $interfaces)],
            ['Documentation %', $docCount > 0 ? round(($docCount / ($propertiesCount + $this->analyzeCodeComplexity($content)['methods'])) * 100) : 0, $docCount > 0 ? 'OK' : 'Consider adding documentation']
        ]);
    }

    private function findAllProjectModels(): array
    {
        $modelFiles = [];

        // Common directories where models might be located
        $directories = [
            'app/Models',
            'app',
            'modules', // For modular applications
            'src'      // For package development
        ];

        foreach ($directories as $directory) {
            $basePath = base_path($directory);

            if ( ! is_dir($basePath)) {
                continue;
            }

            // Recursively find all PHP files in the directory
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($basePath)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && 'php' === $file->getExtension()) {
                    $filePath = $file->getPathname();
                    $content = $this->fileSystem->get($filePath);

                    // Check if file contains a class that extends Model
                    if (str_contains($content, 'class') &&
                        (str_contains($content, 'extends Model') ||
                            str_contains($content, 'Illuminate\Database\Eloquent\Model'))) {
                        $modelFiles[] = $filePath;
                    }
                }
            }
        }

        return $modelFiles;
    }

    private function findModelFiles(string $path): array
    {
        $basePath = base_path($path);
        $files = glob($basePath.'/*.php');

        return array_filter($files, function ($file) {
            $content = $this->fileSystem->get($file);
            return str_contains($content, 'class') &&
                (str_contains($content, 'extends Model') ||
                    str_contains($content, 'Illuminate\Database\Eloquent\Model'));
        });
    }

    private function batchProcessModels(array $modelFiles): void
    {
        $models = [];
        foreach ($modelFiles as $modelFile) {
            $content = $this->fileSystem->get($modelFile);
            $namespace = $this->extractNamespace($content);
            $className = $this->extractClassName($content);
            $fullClassName = $namespace.'\\'.$className;

            if ( ! class_exists($fullClassName)) {
                require_once $modelFile;
            }

            if (is_subclass_of($fullClassName, 'Illuminate\Database\Eloquent\Model')) {
                $models[] = new $fullClassName();
            }
        }

        // Process models in batches
        foreach (array_chunk($models, 10) as $modelBatch) {
            foreach ($modelBatch as $model) {
                $this->auditModel($model);
            }
        }
    }

    private function auditModel($model): void
    {
        $fields = $this->extractModelFields($model);
        $relations = $this->extractModelRelations($model);
        $className = get_class($model);

        $this->modelInfo[$className] = [
            'fields' => $fields,
            'relations' => $relations
        ];
    }

    private function generateDocumentationOutput(string $outputPath): string
    {
        $extension = mb_strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));

        return match ($extension) {
            'json' => $this->generateJsonDocumentation(),
            'md', 'markdown' => $this->generateMarkdownDocumentation(),
            default => $this->generateTextDocumentation(),
        };
    }

    private function generateJsonDocumentation(): string
    {
        $documentation = [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_models' => count($this->modelInfo),
            'encoding' => 'UTF-8',
            'models' => [],
        ];

        foreach ($this->modelInfo as $modelName => $info) {
            $documentation['models'][$modelName] = [
                'fields' => array_map(
                    fn ($field) => [
                        'name' => mb_convert_encoding($field->getName(), 'UTF-8', 'auto'),
                        'type' => mb_convert_encoding($field->getType(), 'UTF-8', 'auto'),
                        'is_relation' => $field->isRelation(),
                    ],
                    $info['fields']
                ),
                'relations' => array_map(
                    fn ($relation) => [
                        'name' => mb_convert_encoding($relation['name'], 'UTF-8', 'auto'),
                        'type' => mb_convert_encoding($relation['type'], 'UTF-8', 'auto'),
                        'related_model' => mb_convert_encoding($relation['related_model'] ?? 'Unknown', 'UTF-8', 'auto'),
                    ],
                    $info['relations']
                ),
            ];
        }

        return json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function generateMarkdownDocumentation(): string
    {
        $output = "# Database Schema Documentation\n\n";
        $output .= "Generated on: ".date('Y-m-d H:i:s')."\n\n";

        // Summary section
        $output .= "## Overview\n\n";
        $output .= "Total Models: ".count($this->modelInfo)."\n\n";

        // Models table
        $output .= "## Models Summary\n\n";
        $output .= "| Model | Fields | Relations |\n";
        $output .= "|-------|---------|------------|\n";

        foreach ($this->modelInfo as $modelName => $info) {
            $output .= sprintf(
                "| %s | %d | %d |\n",
                $modelName,
                count($info['fields']),
                count($info['relations'])
            );
        }

        // Detailed information
        $output .= "\n## Model Details\n\n";
        foreach ($this->modelInfo as $modelName => $info) {
            $output .= "### {$modelName}\n\n";

            // Fields table
            $output .= "#### Fields\n\n";
            $output .= "| Name | Type | Is Relation |\n";
            $output .= "|------|------|-------------|\n";
            foreach ($info['fields'] as $field) {
                $output .= sprintf(
                    "| %s | %s | %s |\n",
                    $field->getName(),
                    $field->getType(),
                    $field->isRelation() ? 'Yes' : 'No'
                );
            }

            // Relations table
            if ( ! empty($info['relations'])) {
                $output .= "\n#### Relations\n\n";
                $output .= "| Name | Type | Related To |\n";
                $output .= "|------|------|------------|\n";
                foreach ($info['relations'] as $relation) {
                    $output .= sprintf(
                        "| %s | %s | %s |\n",
                        $relation['name'],
                        $relation['type'],
                        $relation['related_model'] ?? 'Unknown'
                    );
                }
            }
            $output .= "\n";
        }

        return $output;
    }

    private function generateTextDocumentation(): string
    {
        $output = "DATABASE SCHEMA DOCUMENTATION\n";
        $output .= str_repeat("=", 30)."\n\n";
        $output .= "Generated on: ".date('Y-m-d H:i:s')."\n\n";

        $output .= "OVERVIEW\n";
        $output .= str_repeat("-", 8)."\n";
        $output .= "Total Models: ".count($this->modelInfo)."\n\n";

        foreach ($this->modelInfo as $modelName => $info) {
            $output .= "MODEL: {$modelName}\n";
            $output .= str_repeat("-", mb_strlen($modelName) + 7)."\n\n";

            $output .= "Fields:\n";
            foreach ($info['fields'] as $field) {
                $output .= sprintf(
                    "- %s (%s)%s\n",
                    $field->getName(),
                    $field->getType(),
                    $field->isRelation() ? " [Relation]" : ""
                );
            }

            if ( ! empty($info['relations'])) {
                $output .= "\nRelations:\n";
                foreach ($info['relations'] as $relation) {
                    $output .= sprintf(
                        "- %s: %s -> %s\n",
                        $relation['name'],
                        $relation['type'],
                        $relation['related_model'] ?? 'Unknown'
                    );
                }
            }
            $output .= "\n";
        }

        return $output;
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

            if ( ! empty($info['relations'])) {
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
                if ( ! isset($relation['related_model']) || 'Unknown' === $relation['related_model']) {
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
