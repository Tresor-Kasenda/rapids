<?php

declare(strict_types=1);

namespace Rapids\Rapids\Console;

use Illuminate\Console\Command;

final class RapidsInstallation extends Command
{
    protected $signature = 'rapids:install';

    protected $description = 'Install Rapids package';

    public function handle(): void
    {
        \Laravel\Prompts\info('Installing Rapids package...');
    }
}
