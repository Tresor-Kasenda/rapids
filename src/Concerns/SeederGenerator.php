<?php

namespace Rapids\Rapids\Concerns;

use Illuminate\Support\Facades\File;

class SeederGenerator
{
    public function __construct(
        public string $modelName
    )
    {
    }

    public function generateSeeder(): void
    {
        $seederStub = File::get(config('rapids.stubs.migration.seeder'));

        $seederContent = str_replace(
            ['{{ class }}', '{{ model }}'],
            ["{$this->modelName}Seeder", $this->modelName],
            $seederStub
        );

        File::put(database_path("seeders/{$this->modelName}Seeder.php"), $seederContent);
    }
}
