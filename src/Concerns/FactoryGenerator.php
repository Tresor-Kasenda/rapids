<?php

namespace Rapids\Rapids\Concerns;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Rapids\Rapids\Application\UseCase\GetModelFieldsUseCase;
use Rapids\Rapids\Infrastructure\Repository\LaravelModelRepository;
use Rapids\Rapids\Infrastructure\Repository\LaravelSchemaRepository;
use Rapids\Rapids\Services\ModelFieldsService;
use RuntimeException;
use function Laravel\Prompts\text;

class FactoryGenerator
{
    /**
     * Maps field types to faker methods
     */
    private const TYPE_MAPPINGS = [
        'string' => "'{field}' => \$this->faker->words(3, true),",
        'text' => "'{field}' => \$this->faker->paragraph,",
        'integer' => "'{field}' => \$this->faker->numberBetween(1, 1000),",
        'bigInteger' => "'{field}' => \$this->faker->numberBetween(1000, 9999999),",
        'float' => "'{field}' => \$this->faker->randomFloat(2, 1, 1000),",
        'decimal' => "'{field}' => \$this->faker->randomFloat(2, 1, 1000),",
        'boolean' => "'{field}' => \$this->faker->boolean,",
        'date' => "'{field}' => \$this->faker->date(),",
        'datetime' => "'{field}' => \$this->faker->dateTime(),",
        'timestamp' => "'{field}' => \$this->faker->dateTime(),",
        'json' => "'{field}' => ['key' => \$this->faker->word],",
        'uuid' => "'{field}' => \$this->faker->uuid,",
        'email' => "'{field}' => \$this->faker->safeEmail,",
        'phone' => "'{field}' => \$this->faker->phoneNumber,",
        'url' => "'{field}' => \$this->faker->url,",
        'code' => "'{field}' => \$this->faker->unique()->bothify('CODE-####'),",
    ];

    /**
     * @param string $modelName The name of the model
     * @param array $selectedFields The selected fields for the model
     * @param array $relationFields The relation fields for the model
     * @param bool $interactive Whether to use interactive prompts (default: true)
     */
    public function __construct(
        public string         $modelName,
        public array          $selectedFields,
        public array          $relationFields,
        private readonly bool $interactive = true
    )
    {
    }

    /**
     * Generate a factory file for the model
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function generateFactory(): void
    {
        try {
            $factoryStub = File::get(config('rapids.stubs.migration.factory'));
            $fields = $this->getModelFields();
            $factoryFields = $this->buildFactoryFields($fields);
            $factoryContent = $this->generateFactoryContent($factoryStub, $factoryFields);

            $factoryPath = database_path("factories/{$this->modelName}Factory.php");
            File::put($factoryPath, $factoryContent);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to generate factory: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the model fields
     *
     * @return array
     */
    private function getModelFields(): array
    {
        // Create dependencies for ModelFieldsService
        $modelRepository = new LaravelModelRepository();
        $schemaRepository = new LaravelSchemaRepository();
        $useCase = new GetModelFieldsUseCase($modelRepository, $schemaRepository);

        // Create service with correct parameters
        $service = new ModelFieldsService($this->modelName, $useCase);
        return $service->getModelFields();
    }

    /**
     * Build factory fields based on field types
     *
     * @param array $fields
     * @return array
     */
    private function buildFactoryFields(array $fields): array
    {
        $factoryFields = [];

        foreach ($fields as $field => $type) {
            if (str_ends_with($field, '_id')) {
                $factoryFields[] = $this->handleRelationField($field);
            } else {
                $factoryFields[] = $this->handleRegularField($field, $type);
            }
        }

        return $factoryFields;
    }

    /**
     * Handle relation field
     *
     * @param string $field
     * @return string
     */
    private function handleRelationField(string $field): string
    {
        $suggestedModel = Str::studly(Str::beforeLast($field, '_id'));
        $relatedModel = $suggestedModel;

        if ($this->interactive) {
            $relatedModel = text(
                label: "Enter related model name for {$field}",
                placeholder: $suggestedModel,
                default: $suggestedModel,
                required: true
            );
        }

        return "'{$field}' => \\App\\Models\\{$relatedModel}::factory(),";
    }

    /**
     * Handle regular field based on type
     *
     * @param string $field
     * @param string $type
     * @param array $options
     * @return string
     */
    private function handleRegularField(string $field, string $type, array $options = []): string
    {
        if (isset(self::TYPE_MAPPINGS[$type])) {
            return str_replace('{field}', $field, self::TYPE_MAPPINGS[$type]);
        } elseif ($type === 'enum') {
            $values = $options['values'] ?? [];
            return "'{$field}' => \$this->faker->randomElement(['" . implode("', '", $values) . "']),";
        }

        return "'{$field}' => \$this->faker->word,";
    }

    /**
     * Generate factory content
     *
     * @param string $stub
     * @param array $factoryFields
     * @return string
     */
    private function generateFactoryContent(string $stub, array $factoryFields): string
    {
        return str_replace(
            ['{{ namespace }}', '{{ model }}', '{{ fields }}'],
            ['Database\\Factories', $this->modelName, implode("\n            ", $factoryFields)],
            $stub
        );
    }
}
