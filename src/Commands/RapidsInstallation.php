<?php

namespace Rapids\Rapids\Commands;

use Illuminate\Console\Command;

class RapidsInstallation extends Command
{
    protected $signature = 'rapids:install';

    protected $description = 'Install Rapids package';

    public function handle(): void
    {
        \Laravel\Prompts\info('Installing Rapids package...');
    }
}
