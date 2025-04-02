<?php

declare(strict_types=1);

namespace Rapids\Rapids\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Rapids\Rapids\Concerns\ModelFieldsGenerator;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class RapidCrud extends Command
{
    protected $signature = 'generate:crud {path?} {--model=}';

    protected $description = '
        Generate CRUD operations for a model.
        The path should be in the format "admin.users".
        The model name can be provided with the --model option.
    ';

    protected string $modelName;
    protected array $selectedFields = [];
    protected array $relationFields = [];
    private ModelFieldsGenerator $modelFields;

    public function __construct()
    {
        parent::__construct();
        //$this->modelFields = new ModelFieldsGenerator($this);
    }

    public function handle(): void
    {
        $path = $this->argument('path') ?? text(
            label: "Enter the name of Model",
            placeholder: 'e.g. admin.users',
            required: true,
            validate: fn(string $value) => match (true) {
                mb_strlen($value) < 3 => 'The path must be at least 3 characters.',
                !str_contains($value, '.') => 'The path must contain at least one dot (e.g. admin.users)',
                default => null
            }
        );

        // Get all PHP files in Models directory
        $modelPath = app_path('Models');
        $modelFiles = array_map(
            fn($file) => pathinfo($file, PATHINFO_FILENAME),
            glob($modelPath . '/*.php')
        );

        // Filter existing models
        $availableModels = array_filter($modelFiles, fn($model) => class_exists("App\\Models\\{$model}"));

        // Allow creating new model if none exist
        if (empty($availableModels)) {
            info('No existing models found. You can create a new one.');
        }

        // Get model name from input or create new
        $modelName = text(
            label: 'Enter model name (existing or new)',
            placeholder: 'e.g. User, Post, Product',
            required: true,
            validate: fn(string $value) => match (true) {
                mb_strlen($value) < 2 => 'The model name must be at least 2 characters.',
                !preg_match('/^[A-Za-z]+$/', $value) => 'The model name must contain only letters.',
                default => null
            }
        );

        $this->modelName = ucfirst($modelName);

        // Check if model exists
        if (!class_exists("App\\Models\\{$this->modelName}")) {
            if (confirm(
                label: "Model {$this->modelName} doesn't exist. Would you like to create it?",
                default: true
            )) {
                $this->call('rapids:model');
                $this->refreshApplication(); // Add this line
            } else {
                error("Cannot proceed without a valid model.");
                return;
            }
        }

        // Get all model fields
        $allModelFields = $this->getModelFields();

        // Generate columns for table view
        $this->generateColumns();

        // Let user select fields for form
        $formFields = $this->selectFormFields();

        // Configure each form field
        $formConfig = $this->configureFormFields($formFields);

        // Generate Filament form fields
        $formFieldsCode = $this->generateFilamentFormFields($formConfig);

        // Generate shared form trait
        $this->generateFormTrait($formFieldsCode);

        $this->generateCrudFiles($path);
        $this->generateBladeFiles($path);
        $this->addRoutesToWeb($path);
        info('CRUD operations generated and routes added successfully.');
    }

    protected function refreshApplication(): void
    {
        info('Refreshing application...');

        $commands = [
            'optimize:clear',
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'composer dump-autoload',
        ];

        collect($commands)->each(function ($command): void {
            if ('composer dump-autoload' === $command) {
                info('Running composer dump-autoload...');
                exec('composer dump-autoload');
            } else {
                info("Running {$command}...");
                $this->call($command);
            }
        });

        info('Application refreshed successfully.');
    }

    protected function getModelFields(): array
    {
        return $this->modelFields->getModelFields();
    }

    protected function generateColumns(): string
    {
        if (empty($this->selectedFields)) {
            $this->selectedFields = $this->getModelFields();
        }

        $model = "App\\Models\\{$this->modelName}";
        $instance = new $model();
        $table = $instance->getTable();
        $columns = [];

        foreach ($this->selectedFields as $field) {
            // Gestion des relations
            if (str_ends_with($field, '_id') && isset($this->relationFields[$field])) {
                $relationName = Str::beforeLast($field, '_id');
                $displayField = $this->relationFields[$field];

                $columns[] = "TextColumn::make('{$relationName}.{$displayField}')\n" .
                    "                        ->label('" . Str::title(str_replace('_', ' ', $relationName)) . "')\n" .
                    "                        ->searchable()\n" .
                    "                        ->sortable()\n" .
                    "                        ->toggleable(isToggledHiddenByDefault: false)";
                continue;
            }

            // Récupération du type de colonne
            $columnType = Schema::getColumnType($table, $field);

            // Génération de la colonne en fonction du type
            $column = match ($columnType) {
                'boolean' => "IconColumn::make('{$field}')\n" .
                    "                        ->label('" . Str::title(str_replace('_', ' ', $field)) . "')\n" .
                    "                        ->boolean()\n" .
                    "                        ->sortable()\n" .
                    "                        ->toggleable(isToggledHiddenByDefault: false)",

                'date' => "DateColumn::make('{$field}')\n" .
                    "                        ->label('" . Str::title(str_replace('_', ' ', $field)) . "')\n" .
                    "                        ->date('d-m-Y')\n" .
                    "                        ->sortable()\n" .
                    "                        ->toggleable(isToggledHiddenByDefault: false)",

                'datetime' => "DatetimeColumn::make('{$field}')\n" .
                    "                        ->label('" . Str::title(str_replace('_', ' ', $field)) . "')\n" .
                    "                        ->dateTime('d-m-Y H:i')\n" .
                    "                        ->sortable()\n" .
                    "                        ->toggleable(isToggledHiddenByDefault: false)",

                'decimal', 'float', 'double' => "TextColumn::make('{$field}')\n" .
                    "                        ->label('" . Str::title(str_replace('_', ' ', $field)) . "')\n" .
                    "                        ->numeric()\n" .
                    "                        ->sortable()\n" .
                    "                        ->toggleable(isToggledHiddenByDefault: false)",

                'integer', 'bigint' => "TextColumn::make('{$field}')\n" .
                    "                        ->label('" . Str::title(str_replace('_', ' ', $field)) . "')\n" .
                    "                        ->numeric()\n" .
                    "                        ->sortable()\n" .
                    "                        ->toggleable(isToggledHiddenByDefault: false)",

                'enum' => "BadgeColumn::make('{$field}')\n" .
                    "                        ->label('" . Str::title(str_replace('_', ' ', $field)) . "')\n" .
                    "                        ->enum([\n" .
                    "                            // Add your enum values here\n" .
                    "                        ])\n" .
                    "                        ->sortable()\n" .
                    "                        ->toggleable(isToggledHiddenByDefault: false)",

                default => "TextColumn::make('{$field}')\n" .
                    "                        ->label('" . Str::title(str_replace('_', ' ', $field)) . "')\n" .
                    "                        ->searchable()\n" .
                    "                        ->sortable()\n" .
                    "                        ->toggleable(isToggledHiddenByDefault: false)",
            };

            // Gestion spéciale des champs image/fichier basée sur le nom
            if (Str::contains($field, ['image', 'photo', 'avatar', 'picture'])) {
                $column = "ImageColumn::make('{$field}')\n" .
                    "                        ->label('" . Str::title(str_replace('_', ' ', $field)) . "')\n" .
                    "                        ->circular()\n" .
                    "                        ->toggleable(isToggledHiddenByDefault: false)";
            } elseif (Str::contains($field, ['file', 'document', 'pdf'])) {
                $column = "TextColumn::make('{$field}')\n" .
                    "                        ->label('" . Str::title(str_replace('_', ' ', $field)) . "')\n" .
                    "                        ->icon('heroicon-o-document')\n" .
                    "                        ->toggleable(isToggledHiddenByDefault: false)";
            }

            $columns[] = $column;
        }

        return implode(",\n                    ", $columns);
    }

    protected function selectFormFields(): array
    {
        info('Configuring form fields for ' . $this->modelName);

        // Get fields excluding common system fields
        $availableFields = array_diff($this->selectedFields, ['id', 'created_at', 'updated_at', 'deleted_at']);

        // Let user select fields for form (will be used in both store and update)
        return multiselect(
            label: "Select fields to include in the {$this->modelName} form",
            options: collect($availableFields)
                ->mapWithKeys(fn($field) => [$field => Str::title(str_replace('_', ' ', $field))])
                ->all(),
            default: $availableFields,
            required: true
        );
    }

    protected function configureFormFields(array $selectedFormFields): array
    {
        $configuredFields = [];

        foreach ($selectedFormFields as $field) {
            // Auto-detect field type
            $fieldType = $this->determineFieldType($field);

            // Allow user to customize field type if needed
            $selectedType = select(
                label: "Field type for '{$field}'",
                options: [
                    'text' => 'Text Input',
                    'textarea' => 'Text Area',
                    'richEditor' => 'Rich Text Editor',
                    'select' => 'Select Dropdown',
                    'toggle' => 'Toggle Switch',
                    'date' => 'Date Picker',
                    'dateTime' => 'DateTime Picker',
                    'file' => 'File Upload',
                    'number' => 'Number Input',
                    'email' => 'Email Input',
                    'password' => 'Password Input',
                    'tel' => 'Phone Input',
                    'url' => 'URL Input',
                    'color' => 'Color Picker',
                ],
                default: $fieldType
            );

            // Configure field validation
            $validations = [];

            // Required field?
            $isRequired = confirm(
                label: "Is '{$field}' field required?",
                default: true
            );

            if ($isRequired) {
                $validations[] = 'required';
            } else {
                $validations[] = 'nullable';
            }

            // Add type-specific validations
            switch ($selectedType) {
                case 'email':
                    $validations[] = 'email';
                    break;
                case 'url':
                    $validations[] = 'url';
                    break;
                case 'number':
                    $validations[] = 'numeric';
                    $min = confirm("Add minimum value limit?") ? text("Enter minimum value:") : null;
                    $max = confirm("Add maximum value limit?") ? text("Enter maximum value:") : null;
                    if (null !== $min) {
                        $validations[] = "min:{$min}";
                    }
                    if (null !== $max) {
                        $validations[] = "max:{$max}";
                    }
                    break;
                case 'text':
                    $minChars = confirm("Add minimum length limit?") ? text("Enter minimum characters:") : null;
                    $maxChars = confirm("Add maximum length limit?") ? text("Enter maximum characters:", default: "255") : "255";
                    if (null !== $minChars) {
                        $validations[] = "min:{$minChars}";
                    }
                    if (null !== $maxChars) {
                        $validations[] = "max:{$maxChars}";
                    }
                    break;
            }

            $configuredFields[$field] = [
                'type' => $selectedType,
                'validation' => $validations,
                'is_relation' => str_ends_with($field, '_id')
            ];

            // Handle relation fields
            if ($configuredFields[$field]['is_relation']) {
                $relationName = Str::beforeLast($field, '_id');
                $configuredFields[$field]['relation'] = $relationName;
                $configuredFields[$field]['display_field'] = $this->relationFields[$field] ?? 'name';
            }
        }

        return $configuredFields;
    }

    protected function determineFieldType(string $field): string
    {
        $model = "App\\Models\\{$this->modelName}";
        $instance = new $model();
        $table = $instance->getTable();
        $columnType = Schema::getColumnType($table, $field);

        // Check field name patterns
        $patterns = [
            'email' => 'email',
            'password' => 'password',
            'phone|mobile|tel' => 'tel',
            'date' => 'date',
            'time' => 'time',
            'datetime' => 'dateTime',
            'description|content|body|notes' => 'textarea',
            'html|editor|rich' => 'richEditor',
            'image|photo|picture|avatar' => 'file',
            'file|document|attachment' => 'file',
            'url|website|link' => 'url',
            'active|status|enabled|is_|has_' => 'toggle',
            'color' => 'color',
        ];

        foreach ($patterns as $pattern => $type) {
            if (preg_match('/(' . $pattern . ')/i', $field)) {
                return $type;
            }
        }

        // If no pattern match, use database column type
        return match ($columnType) {
            'boolean' => 'toggle',
            'tinyint' => 'toggle',
            'text', 'mediumtext', 'longtext' => 'textarea',
            'decimal', 'float', 'double', 'integer', 'bigint' => str_ends_with($field, '_id') ? 'select' : 'number',
            'date' => 'date',
            'datetime' => 'dateTime',
            'json', 'array' => 'keyValue',
            default => 'text'
        };
    }

    protected function generateFilamentFormFields(array $formConfig): string
    {
        $fields = [];

        foreach ($formConfig as $name => $config) {
            $fieldCode = match ($config['type']) {
                'text' => "Forms\Components\TextInput::make('{$name}')",
                'textarea' => "Forms\Components\Textarea::make('{$name}')",
                'richEditor' => "Forms\Components\RichEditor::make('{$name}')",
                'select' => $this->generateSelectField($name, $config),
                'toggle' => "Forms\Components\Toggle::make('{$name}')",
                'date' => "Forms\Components\DatePicker::make('{$name}')",
                'dateTime' => "Forms\Components\DateTimePicker::make('{$name}')",
                'file' => "Forms\Components\FileUpload::make('{$name}')"
                    . "->disk('public')"
                    . "->directory('" . Str::plural(Str::snake($this->modelName)) . "')",
                'number' => "Forms\Components\TextInput::make('{$name}')->numeric()",
                'email' => "Forms\Components\TextInput::make('{$name}')->email()",
                'password' => "Forms\Components\TextInput::make('{$name}')->password()->autocomplete('new-password')",
                'tel' => "Forms\Components\TextInput::make('{$name}')->tel()",
                'url' => "Forms\Components\TextInput::make('{$name}')->url()",
                'color' => "Forms\Components\ColorPicker::make('{$name}')",
                default => "Forms\Components\TextInput::make('{$name}')",
            };

            // Add validations
            if (in_array('required', $config['validation'])) {
                $fieldCode .= "->required()";
            } else {
                $fieldCode .= "->nullable()";
            }

            // Add other validations
            foreach ($config['validation'] as $rule) {
                if ('required' !== $rule && 'nullable' !== $rule) {
                    if (str_starts_with($rule, 'min:')) {
                        $min = mb_substr($rule, 4);
                        if ('number' === $config['type']) {
                            $fieldCode .= "->minValue({$min})";
                        } else {
                            $fieldCode .= "->minLength({$min})";
                        }
                    } elseif (str_starts_with($rule, 'max:')) {
                        $max = mb_substr($rule, 4);
                        if ('number' === $config['type']) {
                            $fieldCode .= "->maxValue({$max})";
                        } else {
                            $fieldCode .= "->maxLength({$max})";
                        }
                    }
                }
            }

            // Add label
            $label = Str::title(str_replace('_', ' ', $name));
            $fieldCode .= "->label('{$label}')";

            $fields[] = $fieldCode;
        }

        return implode(",\n", $fields);
    }

    protected function generateSelectField(string $name, array $config): string
    {
        if (!isset($config['relation'])) {
            return "Forms\Components\Select::make('{$name}')";
        }

        $relation = $config['relation'];
        $displayField = $config['display_field'] ?? 'name';
        $relationClass = "App\\Models\\" . Str::studly($relation);

        return "Forms\Components\Select::make('{$name}')"
            . "\n                    ->relationship('{$relation}', '{$displayField}')"
            . "\n                    ->searchable()"
            . "\n                    ->preload()";
    }

    protected function generateFormTrait(string $formFields): void
    {
        $traitsDir = app_path('Concerns');
        File::ensureDirectoryExists($traitsDir);

        $traitContent = <<<PHP
    <?php

    namespace App\\Concerns;

    use Filament\\Forms;
    use Filament\\Forms\\Form;
    use App\\Models\\{$this->modelName};
    use Livewire\\WithFileUploads;

    trait Has{$this->modelName}FormSchema
    {
        use WithFileUploads;

        public function form(Form \$form): Form
        {
            return \$form
                ->schema([
                    {$formFields}
                ])
                ->statePath('data')
                ->model({$this->modelName}::class);
        }
    }
    PHP;

        File::put($traitsDir . "/Has{$this->modelName}FormSchema.php", $traitContent);
    }

    protected function generateCrudFiles($path): void
    {
        $segments = explode('.', $path);
        $lastSegment = ucfirst(end($segments));
        $namespaceParts = array_map('ucfirst', explode('.', $path));
        $basePath = app_path('Livewire/' . implode('/', $namespaceParts));
        File::ensureDirectoryExists($basePath);

        $files = [
            'Lists' . $lastSegment,
            'Store' . $lastSegment,
            'Update' . $lastSegment,
            'Detail' . $lastSegment
        ];

        foreach ($files as $file) {
            $filePath = $basePath . '/' . $file . '.php';

            $stubType = mb_strtolower(preg_replace('/[A-Z][a-z]+$/', '', $file));
            $stubPath = base_path('stubs/livewire/livewire.' . $stubType . '.stub');

            if (!File::exists($stubPath)) {
                $this->error("Stub file not found: {$stubPath}");
                continue;
            }

            $stubContent = File::get($stubPath);

            // Replace namespace with capitalized path
            $stubContent = str_replace('{{ namespace }}', implode('\\', $namespaceParts), $stubContent);
            $viewPath = mb_strtolower(implode('.', explode('.', $path)));
            $stubContent = str_replace('{{ path }}', $viewPath, $stubContent);
            $stubContent = str_replace('{{ model }}', $this->modelName, $stubContent);
            $stubContent = str_replace('{{ model | lower }}', Str::camel($this->modelName), $stubContent);
            $stubContent = str_replace('{{ class }}', $lastSegment, $stubContent);
            $stubContent = str_replace('{{ lastSegment }}', mb_strtolower($lastSegment), $stubContent);


            if ('lists' === $stubType) {
                $stubContent = str_replace('{{ columns }}', $this->generateColumns(), $stubContent);
            }

            File::put($filePath, $stubContent);
        }
    }

    protected function generateBladeFiles(string $path): void
    {
        $segments = explode('.', $path);
        $viewPath = implode('/', $segments);
        $lastSegment = end($segments);

        // Create directories if they don't exist
        $directory = resource_path("views/livewire/{$viewPath}");
        File::ensureDirectoryExists($directory);

        // Create store blade file
        $storeBladeContent = $this->generateFormBlade('store');
        File::put("{$directory}/store-{$lastSegment}.blade.php", $storeBladeContent);

        // Create update blade file
        $updateBladeContent = $this->generateFormBlade('update');
        File::put("{$directory}/update-{$lastSegment}.blade.php", $updateBladeContent);

        // Create index blade file
        $indexBladeContent = $this->generateIndexBlade();
        File::put("{$directory}/list-{$lastSegment}.blade.php", $indexBladeContent);

        // Create show blade file if needed
        $showBladeContent = $this->generateShowBlade();
        File::put("{$directory}/show-{$lastSegment}.blade.php", $showBladeContent);
    }

    protected function generateFormBlade(string $action): string
    {
        $modelVariable = Str::camel($this->modelName);
        $modelTitle = Str::title(Str::snake($this->modelName, ' '));
        $actionTitle = 'store' === $action ? 'Create' : 'Update';

        return <<<BLADE
    <div>
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <div class="px-4 sm:px-0">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">{$actionTitle} {$modelTitle}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {$actionTitle} a new {$modelTitle} with the form below.
                        </p>
                    </div>
                </div>
                <div class="mt-5 md:mt-0 md:col-span-2">
                    <div class="shadow sm:rounded-md sm:overflow-hidden">
                        <form wire:submit.prevent="submit">
                            <div class="px-4 py-5 bg-white space-y-6 sm:p-6">
                                {{ \$this->form }}
                            </div>
                            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    {$actionTitle}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    BLADE;
    }

    protected function generateIndexBlade(): string
    {
        $modelPlural = Str::plural(Str::title(Str::snake($this->modelName, ' ')));

        return <<<BLADE
    <div>
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">{$modelPlural}</h1>
                    <a href="{{ route('{$this->getRoutePrefix()}.{$this->getRouteName()}.store') }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Create {$this->modelName}
                    </a>
                </div>

                <div class="mt-4">
                    {{ \$this->table }}
                </div>
            </div>
        </div>
    </div>
    BLADE;
    }

    protected function getRoutePrefix(): string
    {
        $segments = explode('.', $this->argument('path') ?? '');
        return $segments[0] ?? 'admin';
    }

    protected function getRouteName(): string
    {
        $segments = explode('.', $this->argument('path') ?? '');
        return end($segments);
    }

    protected function generateShowBlade(): string
    {
        $modelTitle = Str::title(Str::snake($this->modelName, ' '));

        return <<<BLADE
    <div>
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 sm:px-0">
                <h3 class="text-lg font-medium leading-6 text-gray-900">{$modelTitle} Details</h3>
            </div>

            <div class="mt-4">
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 flex justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Information</h3>
                        </div>
                        <div>
                            <a href="{{ route('{$this->getRoutePrefix()}.{$this->getRouteName()}.update', \${$this->getLowerModelName()}) }}"
                               class="text-indigo-600 hover:text-indigo-900">Edit</a>
                        </div>
                    </div>
                    <div class="border-t border-gray-200">
                        <dl>
                            @foreach(\${$this->getLowerModelName()}->toArray() as \$key => \$value)
                                @if(!in_array(\$key, ['id', 'created_at', 'updated_at', 'deleted_at']))
                                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">{{ Str::title(str_replace('_', ' ', \$key)) }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ \$value }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
    BLADE;
    }

    protected function getLowerModelName(): string
    {
        return Str::camel($this->modelName);
    }

    protected function addRoutesToWeb($path): void
    {
        $segments = explode('.', $path);
        $lastSegment = mb_strtolower(end($segments));
        $routePath = base_path('routes/web.php');

        $namespaceParts = array_map('ucfirst', explode('.', $path));
        $namespacePath = implode('\\', $namespaceParts);

        // Get model variable name in lowercase
        $modelVariable = Str::camel(Str::singular($this->modelName));

        $routeContent = "\nRoute::group(['prefix' => '" . $lastSegment . "', 'as' => '" . $lastSegment . ".'], function (): void {\n" .
            "    Route::get('/', [App\\Livewire\\{$namespacePath}\\Lists" . ucfirst($lastSegment) . "::class])->name('{$lastSegment}.index');\n" .
            "    Route::get('/create', [App\\Livewire\\{$namespacePath}\\Store" . ucfirst($lastSegment) . "::class])->name('{$lastSegment}.create');\n" .
            "    Route::get('/{{$modelVariable}}/edit', [App\\Livewire\\{$namespacePath}\\Update" . ucfirst($lastSegment) . "::class])->name('{$lastSegment}.edit');\n" .
            "    Route::get('/{{$modelVariable}}', [App\\Livewire\\{$namespacePath}\\Detail" . ucfirst($lastSegment) . "::class])->name('{$lastSegment}.show');\n" .
            "});\n";

        File::append($routePath, $routeContent);
    }

    /**
     * @return string
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * @return array
     */
    public function getSelectedFields(): array
    {
        return $this->selectedFields;
    }

    /**
     * @param array $selectedFields
     */
    public function setSelectedFields(array $selectedFields): void
    {
        $this->selectedFields = $selectedFields;
    }

    /**
     * @param array $relationFields
     */
    public function setRelationFields(array $relationFields): void
    {
        $this->relationFields = $relationFields;
    }
}
