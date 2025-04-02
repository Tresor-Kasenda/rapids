<?php

<<<<<<< HEAD
declare(strict_types=1);

=======
>>>>>>> c7f7ce7 (Restauration du projet et mise à jour)
return [
    'paths' => [
        'models' => app_path('Models'),
        'livewire' => app_path('Livewire/Pages'),
        'views' => resource_path('views/livewire/pages'),
    ],
    'namespace' => [
        'models' => 'App\\Models',
        'livewire' => 'App\\Livewire\\Pages',
    ],
    'stubs' => [
        'migration' => [
<<<<<<< HEAD
            'model' => __DIR__.'/../stubs/model.stub',
            'migration' => __DIR__.'/../stubs/migration.create.stub',
            'factory' => __DIR__.'/../stubs/factory.stub',
            'seeder' => __DIR__.'/../stubs/seeder.stub',
            'alter' => __DIR__.'/../stubs/alter.stub',
        ],
        'class' => [
            'Lists' => __DIR__.'/../stubs/livewire/livewire.lists.stub',
            'View' => __DIR__.'/../stubs/livewire/livewire.detail.stub',
            'Store' => __DIR__.'/../stubs/livewire/livewire.store.stub',
            'Update' => __DIR__.'/../stubs/livewire/livewire.update.stub',
        ],
        'view_list' => __DIR__.'/../stubs/view-list.stub',
        'view_show' => __DIR__.'/../stubs/view-detail.stub',
        'view_store' => __DIR__.'/../stubs/view-store.stub',
        'view_update' => __DIR__.'/../stubs/view-update.stub',
=======
            'model' => 'stubs/model.stub',
            'migration' => 'stubs/migration.create.stub',
            'factory' => 'stubs/factory.stub',
            'seeder' => 'stubs/seeder.stub',
            'alter' => 'stubs/alter.stub',
        ],
        'class' => [
            'list' => 'stubs/livewire/livewire.lists.stub',
            'detail' => 'stubs/livewire/livewire.detail.stub',
            'store' => 'stubs/livewire/livewire.store.stub',
            'update' => 'stubs/livewire/livewire.update.stub',
        ],
        'view_list' => 'stubs/view-list.stub',
        'view_detail' => 'stubs/view-detail.stub',
        'view_store' => 'stubs/view-store.stub',
        'view_update' => 'stubs/view-update.stub',
>>>>>>> c7f7ce7 (Restauration du projet et mise à jour)
    ],
];
