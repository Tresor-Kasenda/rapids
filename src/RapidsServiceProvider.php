<?php

declare(strict_types=1);

namespace Rapids\Rapids;

use Illuminate\Support\ServiceProvider;
use Rapids\Rapids\Application\Port\FileSystemPort;
use Rapids\Rapids\Console\RapidsModels;
use Rapids\Rapids\Contract\FileSystemInterface;
use Rapids\Rapids\Contract\ModelInspectorInterface;
use Rapids\Rapids\Contract\PromptServiceInterface;
use Rapids\Rapids\Contract\RelationshipServiceInterface;
use Rapids\Rapids\Infrastructure\Adapter\FileSystemAdapter;
use Rapids\Rapids\Infrastructure\Adapter\LaravelFileSystemAdapter;
use Rapids\Rapids\Infrastructure\Laravel\LaravelFileSystem;
use Rapids\Rapids\Infrastructure\Laravel\LaravelModelInspector;
use Rapids\Rapids\Infrastructure\Laravel\LaravelPromptService;
use Rapids\Rapids\Infrastructure\Laravel\LaravelRelationshipService;

final class RapidsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/rapids.php' => config_path('rapids.php'),
            __DIR__.'/../stubs' => base_path('stubs/vendor/rapids'),
        ], 'rapids');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RapidsModels::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rapids.php',
            'rapids'
        );

        // Bind interfaces to implementations
        $this->app->bind(FileSystemPort::class, LaravelFileSystemAdapter::class);
        $this->app->bind(FileSystemInterface::class, LaravelFileSystem::class);
        $this->app->bind(ModelInspectorInterface::class, LaravelModelInspector::class);
        $this->app->bind(PromptServiceInterface::class, LaravelPromptService::class);
        $this->app->bind(RelationshipServiceInterface::class, LaravelRelationshipService::class);
    }
}
