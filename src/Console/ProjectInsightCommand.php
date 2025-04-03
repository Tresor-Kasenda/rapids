<?php

namespace Rapids\Rapids\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ProjectInsightCommand extends Command
{
    protected $signature = 'project:insight {--format=text : Output format (text/json/markdown)}';
    protected $description = 'Generate comprehensive project insights';

    private array $projectInfo = [];

    public function handle(): int
    {
        $this->gatherProjectInfo();

        $format = $this->option('format');

        match ($format) {
            'json' => $this->line($this->outputJson()),
            'markdown' => $this->line($this->outputMarkdown()),
            default => $this->outputText(),
        };

        return Command::SUCCESS;
    }

    private function gatherProjectInfo(): void
    {
        // Basic project info
        $this->projectInfo['basics'] = [
            'name' => config('app.name'),
            'environment' => app()->environment(),
            'version' => app()->version(),
        ];

        // Routes
        $this->projectInfo['routes'] = collect(Route::getRoutes())->map(function ($route) {
            return [
                'methods' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
            ];
        })->toArray();

        // Controllers
        $this->projectInfo['controllers'] = collect(File::allFiles(app_path('Http/Controllers')))
            ->map(function ($file) {
                return [
                    'name' => $file->getBasename('.php'),
                    'path' => Str::after($file->getPathname(), base_path() . '/'),
                ];
            })->toArray();

        // Models
        $this->projectInfo['models'] = collect(File::allFiles(app_path('Models')))
            ->map(function ($file) {
                $className = 'App\\Models\\' . $file->getBasename('.php');
                if (!class_exists($className)) return null;

                $model = new $className();
                return [
                    'name' => class_basename($className),
                    'table' => $model->getTable(),
                    'fillable' => $model->getFillable(),
                ];
            })->filter()->toArray();

        // Views
        $this->projectInfo['views'] = collect(File::allFiles(resource_path('views')))
            ->map(function ($file) {
                return [
                    'name' => $file->getBasename('.blade.php'),
                    'path' => Str::after($file->getPathname(), resource_path('views/')),
                ];
            })->toArray();

        // Migrations
        $this->projectInfo['migrations'] = collect(File::files(database_path('migrations')))
            ->map(function ($file) {
                return [
                    'name' => $file->getBasename('.php'),
                    'path' => Str::after($file->getPathname(), database_path('migrations/')),
                ];
            })->toArray();

        // Factories
        $this->projectInfo['factories'] = collect(File::allFiles(database_path('factories')))
            ->map(function ($file) {
                return [
                    'name' => $file->getBasename('.php'),
                    'model' => Str::before($file->getBasename('.php'), 'Factory'),
                ];
            })->toArray();
    }

    private function outputJson(): string
    {
        return json_encode($this->projectInfo, JSON_PRETTY_PRINT);
    }

    private function outputMarkdown(): string
    {
        $output = "# Project Documentation\n\n";
        $output .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";

        // Basic Information
        $output .= "## Basic Information\n\n";
        foreach ($this->projectInfo['basics'] as $key => $value) {
            $output .= "- **{$key}**: {$value}\n";
        }

        // Routes
        $output .= "\n## Routes\n\n";
        $output .= "| Method | URI | Name | Action |\n";
        $output .= "|--------|-----|------|--------|\n";
        foreach ($this->projectInfo['routes'] as $route) {
            $output .= sprintf("| %s | %s | %s | %s |\n",
                $route['methods'],
                $route['uri'],
                $route['name'] ?? 'N/A',
                $route['action']
            );
        }

        // Controllers
        $output .= "\n## Controllers\n\n";
        $output .= "| Name | Path |\n";
        $output .= "|------|------|\n";
        foreach ($this->projectInfo['controllers'] as $controller) {
            $output .= sprintf("| %s | %s |\n",
                $controller['name'],
                $controller['path']
            );
        }

        // Models
        $output .= "\n## Models\n\n";
        $output .= "| Name | Table | Fillable Fields |\n";
        $output .= "|------|-------|----------------|\n";
        foreach ($this->projectInfo['models'] as $model) {
            $output .= sprintf("| %s | %s | %s |\n",
                $model['name'],
                $model['table'],
                implode(', ', $model['fillable'])
            );
        }

        // Views
        $output .= "\n## Views\n\n";
        $output .= "| Name | Path |\n";
        $output .= "|------|------|\n";
        foreach ($this->projectInfo['views'] as $view) {
            $output .= sprintf("| %s | %s |\n",
                $view['name'],
                $view['path']
            );
        }

        // Migrations
        $output .= "\n## Migrations\n\n";
        $output .= "| Name | Path |\n";
        $output .= "|------|------|\n";
        foreach ($this->projectInfo['migrations'] as $migration) {
            $output .= sprintf("| %s | %s |\n",
                $migration['name'],
                $migration['path']
            );
        }

        // Factories
        $output .= "\n## Factories\n\n";
        $output .= "| Name | Model |\n";
        $output .= "|------|-------|\n";
        foreach ($this->projectInfo['factories'] as $factory) {
            $output .= sprintf("| %s | %s |\n",
                $factory['name'],
                $factory['model']
            );
        }

        return $output;
    }

    private function outputText(): void
    {
        $this->info('Project Overview');
        $this->line('================');

        // Basic Info
        $this->info('Basic Information:');
        $this->table(['Key', 'Value'], collect($this->projectInfo['basics'])->map(fn($v, $k) => [$k, $v])->toArray());

        // Routes
        $this->newLine();
        $this->info('Routes:');
        $this->table(['Method', 'URI', 'Name', 'Action'], $this->projectInfo['routes']);

        // Controllers
        $this->newLine();
        $this->info('Controllers:');
        $this->table(['Name', 'Path'], $this->projectInfo['controllers']);

        // Models
        $this->newLine();
        $this->info('Models:');
        $this->table(['Name', 'Table', 'Fillable Fields'], array_map(function ($model) {
            return [
                $model['name'],
                $model['table'],
                implode(', ', $model['fillable'])
            ];
        }, $this->projectInfo['models']));

        // Views
        $this->newLine();
        $this->info('Views:');
        $this->table(['Name', 'Path'], $this->projectInfo['views']);

        // Migrations
        $this->newLine();
        $this->info('Migrations:');
        $this->table(['Name', 'Path'], $this->projectInfo['migrations']);

        // Factories
        $this->newLine();
        $this->info('Factories:');
        $this->table(['Name', 'Model'], $this->projectInfo['factories']);
    }
}
