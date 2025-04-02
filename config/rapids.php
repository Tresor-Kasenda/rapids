<?php
/*
 * This file is part of the Rapids package.
 *
 * (c) 2023-2024 Rapid Devs
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

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
            'Lists' => __DIR__ . '/../stubs/livewire/livewire.lists.stub',
            'View' => __DIR__ . '/../stubs/livewire/livewire.detail.stub',
            'Store' => __DIR__ . '/../stubs/livewire/livewire.store.stub',
            'Update' => __DIR__ . '/../stubs/livewire/livewire.update.stub',
        ],
        'view_list' => __DIR__ . '/../stubs/view-list.stub',
        'view_show' => __DIR__ . '/../stubs/view-detail.stub',
        'view_store' => __DIR__ . '/../stubs/view-store.stub',
        'view_update' => __DIR__ . '/../stubs/view-update.stub',
    ],
];
