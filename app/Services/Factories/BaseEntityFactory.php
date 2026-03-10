<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Services\Factories\Concerns\SanitizesAiData;
use Illuminate\Database\Eloquent\Model;

/**
 * Base factory for creating Eloquent models from AI-extracted data.
 *
 * Subclasses declare their structure via fields(), dateFields(), defaults(),
 * and use lifecycle hooks (prepareData, shouldCreate, afterCreate) for
 * custom behavior like nested data flattening or related record creation.
 */
abstract class BaseEntityFactory
{
    use SanitizesAiData;

    /**
     * The Eloquent model class this factory creates.
     *
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * Field names that map 1:1 from the data array to model attributes.
     * Complex mappings should be resolved in prepareData() first.
     *
     * @return list<string>
     */
    abstract protected function fields(): array;

    /**
     * Fields containing date values that should be sanitized.
     * String representations of null ("null", "None", "N/A") are
     * converted to actual null to prevent Carbon parsing failures.
     *
     * @return list<string>
     */
    protected function dateFields(): array
    {
        return [];
    }

    /**
     * Default values for fields when not present in data.
     *
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [];
    }

    /**
     * Field name for storing the raw AI data payload.
     * When set, stores $data[$field] ?? $data as the value.
     */
    protected function rawDataField(): ?string
    {
        return null;
    }

    /**
     * Pre-process and normalize the data array before attribute resolution.
     * Use this to flatten nested structures, resolve foreign keys, etc.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, File $file): array
    {
        return $data;
    }

    /**
     * Determine if the factory should create a record from the given data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function shouldCreate(array $data): bool
    {
        return ! empty($data);
    }

    /**
     * Hook called after the model is successfully created.
     * Use for creating related records (line items, transactions, etc.).
     *
     * @param  array<string, mixed>  $data
     */
    protected function afterCreate(Model $model, array $data, File $file): void {}

    /**
     * Create a model instance from AI-extracted data.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, File $file): ?Model
    {
        $data = $this->prepareData($data, $file);

        if (! $this->shouldCreate($data)) {
            return null;
        }

        $attributes = $this->resolveAttributes($data, $file);
        $model = $this->modelClass()::create($attributes);

        $this->afterCreate($model, $data, $file);

        return $model;
    }

    /**
     * Build the attribute array for model creation from declared fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveAttributes(array $data, File $file): array
    {
        $attributes = [
            'file_id' => $file->id,
            'user_id' => $file->user_id,
        ];

        $defaults = $this->defaults();
        $dateFields = $this->dateFields();

        foreach ($this->fields() as $field) {
            $value = array_key_exists($field, $data)
                ? $data[$field]
                : ($defaults[$field] ?? null);

            if (in_array($field, $dateFields, true)) {
                $value = $this->nullIfEmpty($value);
            }

            $attributes[$field] = $value;
        }

        $rawField = $this->rawDataField();
        if ($rawField !== null) {
            $attributes[$rawField] = $data[$rawField] ?? $data;
        }

        return $attributes;
    }
}
