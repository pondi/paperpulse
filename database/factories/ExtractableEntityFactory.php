<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtractableEntity>
 */
class ExtractableEntityFactory extends Factory
{
    protected $model = ExtractableEntity::class;

    public function definition(): array
    {
        $fileFactory = File::factory()->state([
            'file_type' => 'document',
            'processing_type' => 'document',
        ]);

        return [
            'file_id' => $fileFactory,
            'user_id' => function (array $attributes) {
                return File::query()->findOrFail($attributes['file_id'])->user_id;
            },
            'entity_type' => 'document',
            'entity_id' => function (array $attributes) {
                $file = File::query()->findOrFail($attributes['file_id']);

                return Document::factory()->create([
                    'file_id' => $file->id,
                    'user_id' => $file->user_id,
                ])->id;
            },
            'is_primary' => false,
            'confidence_score' => null,
            'extraction_provider' => 'gemini',
            'extraction_model' => 'gemini-2.0-flash',
            'extraction_metadata' => null,
            'extracted_at' => now(),
        ];
    }
}
