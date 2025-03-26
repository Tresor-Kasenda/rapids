<?php

namespace Rapids\Rapids;

use Illuminate\Support\ServiceProvider;
use Rapids\Rapids\Console\RapidCrud;
use Rapids\Rapids\Console\RapidsInstallation;
use Rapids\Rapids\Console\RapidsModels;

class RapidsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/rapids.php' => config_path('rapids.php'),
            __DIR__ . '/stubs' => base_path('stubs/vendor/rapids'),
        ], 'rapids');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RapidsInstallation::class,
                RapidsModels::class,
                RapidCrud::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/rapids.php', 'rapids'
        );
    }
}
