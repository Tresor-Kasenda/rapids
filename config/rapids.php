<?php

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
            'model' => __DIR__ . '/../stubs/model.stub',
            'migration' => __DIR__ . '/../stubs/migration.create.stub',
            'factory' => __DIR__ . '/../stubs/factory.stub',
            'seeder' => __DIR__ . '/../stubs/seeder.stub',
            'alter' => __DIR__ . '/../stubs/alter.stub',
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
    ],
];
