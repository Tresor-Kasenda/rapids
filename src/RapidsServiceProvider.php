<?php

declare(strict_types=1);

namespace Rapids\Rapids;

use Illuminate\Support\ServiceProvider;
use Rapids\Rapids\Application\Port\FileSystemPort;
use Rapids\Rapids\Console\AntiPatternDetectorCommand;
use Rapids\Rapids\Console\AuditModelsCommand;
use Rapids\Rapids\Console\RapidCrud;
use Rapids\Rapids\Console\RapidsInstallation;
use Rapids\Rapids\Console\RapidsModels;
use Rapids\Rapids\Infrastructure\Adapter\FileSystemAdapter;
use Rapids\Rapids\Infrastructure\Adapter\LaravelFileSystemAdapter;

final class RapidsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/rapids.php' => config_path('rapids.php'),
            __DIR__.'/stubs' => base_path('stubs/vendor/rapids'),
        ], 'rapids');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RapidsInstallation::class,
                RapidsModels::class,
                RapidCrud::class,
                AuditModelsCommand::class,
                AntiPatternDetectorCommand::class
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rapids.php',
            'rapids'
        );

        $this->app->bind(
            FileSystemPort::class,
            FileSystemAdapter::class
        );

        $this->app->bind(FileSystemPort::class, LaravelFileSystemAdapter::class);
    }
}
