<?php

namespace Rapids\Rapids\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;

class AntiPatternDetectorCommand extends Command
{
    protected $signature = 'rapids:detect-antipatterns
                              {--path=app : Path to scan for Laravel files}
                              {--fix : Automatically fix detected issues where possible}';

    protected $description = 'Detect and fix common Laravel anti-patterns';

    private array $issues = [];
    private array $suggestions = [];

    public function handle(): int
    {
        $this->info('Scanning for Laravel anti-patterns...');

        $this->detectNPlusOneQueries();
        $this->detectInefficientQueries();
        $this->detectMissingIndexes();
        $this->detectEagerLoadingIssues();

        $this->displayResults();

        if ($this->option('fix') && !empty($this->issues)) {
            $this->fixIssues();
        }

        return self::SUCCESS;
    }

    private function detectNPlusOneQueries(): void
    {
        $modelFiles = $this->findModelFiles();

        foreach ($modelFiles as $file) {
            $content = file_get_contents($file->getPathname());
            $namespace = $this->extractNamespace($content);
            $className = $this->extractClassName($content);

            if (!$className || !$namespace) continue;

            $fullClassName = $namespace . '\\' . $className;

            if (!class_exists($fullClassName)) continue;

            $reflection = new ReflectionClass($fullClassName);
            if (!$reflection->isSubclassOf(Model::class)) continue;

            // Check for relations without eager loading
            $this->analyzeModelRelations($reflection, $fullClassName, $file);
        }
    }

    private function findModelFiles(): array
    {
        return File::allFiles(app_path('Models'));
    }

    private function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractClassName(string $content): ?string
    {
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function detectInefficientQueries(): void
    {
        $files = File::allFiles($this->option('path'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;

            $content = file_get_contents($file->getPathname());

            // Detect SELECT *
            if (preg_match_all('/DB::table\(.+\)->select\(\s*\*\s*\)/', $content, $matches)) {
                $this->addIssue(
                    'inefficient-select',
                    $file->getPathname(),
                    'Using SELECT * is inefficient. Specify required columns instead.',
                    'Replace SELECT * with specific column names',
                    $matches[0]
                );
            }

            // Detect LIKE with leading wildcard
            if (preg_match_all('/->where\(.+, \'like\', \'%\w+/', $content, $matches)) {
                $this->addIssue(
                    'inefficient-like',
                    $file->getPathname(),
                    'Using LIKE with leading wildcard prevents index usage',
                    'Consider alternative indexing strategies or full-text search',
                    $matches[0]
                );
            }

            // Detect multiple queries in loops
            if (preg_match_all('/(for|foreach|while).+\{.*(?:DB::|->where|->get|->first).+\}/s', $content, $matches)) {
                $this->addIssue(
                    'queries-in-loop',
                    $file->getPathname(),
                    'Database queries inside loops can cause performance issues',
                    'Consider using eager loading or chunking',
                    $matches[0]
                );
            }
        }
    }

    private function addIssue(string $type, string $location, string $description, string $suggestion, array $snippets = []): void
    {
        $this->issues[] = [
            'type' => $type,
            'location' => $location,
            'description' => $description,
            'suggestion' => $suggestion,
            'snippets' => $snippets
        ];
    }

    private function detectMissingIndexes(): void
    {
        $models = $this->findAllModels();

        foreach ($models as $model) {
            $table = (new $model)->getTable();
            if (!Schema::hasTable($table)) continue;

            // Analyze foreign keys
            $foreignKeys = $this->getForeignKeys($table);
            foreach ($foreignKeys as $column) {
                if (!Schema::hasIndex($table, [$column])) {
                    $this->addIssue(
                        'missing-index',
                        $table,
                        "Missing index on foreign key column '$column'",
                        "Add index: \$table->index('$column');",
                        [$column]
                    );
                }
            }

            // Analyze frequently queried columns
            $this->analyzeMostQueriedColumns($table);
        }
    }

    private function getForeignKeys(string $table): array
    {
        $foreignKeys = [];
        $columns = Schema::getColumnListing($table);

        foreach ($columns as $column) {
            if (Str::endsWith($column, '_id')) {
                $foreignKeys[] = $column;
            }
        }

        return $foreignKeys;
    }

    private function analyzeMostQueriedColumns(string $table): void
    {
        // Note: This requires the query log to be enabled
        $queries = DB::getQueryLog();
        $columnCounts = [];

        foreach ($queries as $query) {
            if (stripos($query['query'], $table) === false) continue;

            if (preg_match_all('/where\s+([^\s=]+)/', $query['query'], $matches)) {
                foreach ($matches[1] as $column) {
                    $columnCounts[$column] = ($columnCounts[$column] ?? 0) + 1;
                }
            }
        }

        arsort($columnCounts);
        $frequentColumns = array_slice($columnCounts, 0, 5, true);

        foreach ($frequentColumns as $column => $count) {
            if ($count > 10 && !Schema::hasIndex($table, [$column])) {
                $this->addIssue(
                    'missing-index',
                    $table,
                    "Frequently queried column '$column' has no index",
                    "Consider adding index for frequently queried column",
                    [$column]
                );
            }
        }
    }

    private function detectEagerLoadingIssues(): void
    {
        $controllerFiles = File::allFiles(app_path('Http/Controllers'));

        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file->getPathname());

            // Detect potential N+1 query patterns
            if (preg_match_all('/(\$\w+)->where.+->get\(\).*foreach/s', $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $this->addIssue(
                        'eager-loading',
                        $file->getPathname(),
                        'Potential N+1 query detected in controller',
                        "Consider using with() to eager load relationships",
                        [$match]
                    );
                }
            }
        }
    }

    private function displayResults(): void
    {
        if (empty($this->issues)) {
            $this->info('No anti-patterns detected!');
            return;
        }

        $this->newLine();
        $this->error('Anti-patterns detected:');

        foreach ($this->issues as $issue) {
            $this->newLine();
            $this->line("Location: {$issue['location']}");
            $this->line("Issue: {$issue['description']}");
            $this->line("Suggestion: {$issue['suggestion']}");

            if (!empty($issue['snippets'])) {
                $this->line('Code snippets:');
                foreach ($issue['snippets'] as $snippet) {
                    $this->line("  - {$snippet}");
                }
            }
        }
    }

    private function fixIssues(): void
    {
        $this->info('Attempting to fix detected issues...');

        foreach ($this->issues as $issue) {
            switch ($issue['type']) {
                case 'eager-loading':
                    $this->fixEagerLoading($issue);
                    break;
                case 'inefficient-select':
                    $this->fixIneffientSelect($issue);
                    break;
                case 'missing-index':
                    $this->generateMigrationForIndex($issue);
                    break;
            }
        }
    }

    private function fixEagerLoading(array $issue): void
    {
        $content = file_get_contents($issue['location']);

        // Simple replacement for basic cases
        $content = preg_replace(
            '/(\$\w+)->where(.+)->get\(\)/',
            '$1->with([\'related\'])->where$2->get()',
            $content
        );

        File::put($issue['location'], $content);
        $this->info("Added eager loading in {$issue['location']}");
    }

    private function fixIneffientSelect(array $issue): void
    {
        $content = file_get_contents($issue['location']);

        // Replace SELECT * with specific columns
        $content = preg_replace(
            '/select\(\s*\*\s*\)/',
            'select([\'id\', \'created_at\', \'updated_at\'])', // Default columns
            $content
        );

        File::put($issue['location'], $content);
        $this->info("Replaced SELECT * with specific columns in {$issue['location']}");
    }

    private function generateMigrationForIndex(array $issue): void
    {
        $table = $issue['location'];
        $column = $issue['snippets'][0];

        $migration = "<?php\n\n";
        $migration .= "use Illuminate\Database\Migrations\Migration;\n";
        $migration .= "use Illuminate\Database\Schema\Blueprint;\n";
        $migration .= "use Illuminate\Support\Facades\Schema;\n\n";
        $migration .= "class AddIndexTo{$table}Table extends Migration\n";
        $migration .= "{\n";
        $migration .= "    public function up()\n";
        $migration .= "    {\n";
        $migration .= "        Schema::table('{$table}', function (Blueprint \$table) {\n";
        $migration .= "            \$table->index('{$column}');\n";
        $migration .= "        });\n";
        $migration .= "    }\n\n";
        $migration .= "    public function down()\n";
        $migration .= "    {\n";
        $migration .= "        Schema::table('{$table}', function (Blueprint \$table) {\n";
        $migration .= "            \$table->dropIndex(['{$column}']);\n";
        $migration .= "        });\n";
        $migration .= "    }\n";
        $migration .= "}\n";

        $filename = date('Y_m_d_His') . "_add_index_to_{$table}_table.php";
        File::put(database_path("migrations/{$filename}"), $migration);

        $this->info("Generated migration for adding index on {$table}.{$column}");
    }
}
