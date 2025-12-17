<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => File::factory()->state([
                'file_type' => 'document',
                'processing_type' => 'document',
            ]),
            'user_id' => function (array $attributes) {
                return File::query()->findOrFail($attributes['file_id'])->user_id;
            },
            'category_id' => null,
            'title' => fake()->sentence(6),
            'content' => fake()->optional()->paragraphs(3, true),
            'description' => fake()->optional()->sentence(),
            'note' => fake()->optional()->sentence(),
            'summary' => fake()->optional()->sentence(),
            'document_type' => 'other',
            'document_date' => fake()->optional()->date(),
            'metadata' => null,
            'extracted_text' => null,
            'ai_summary' => null,
            'ai_entities' => null,
            'language' => null,
            'page_count' => 1,
        ];
    }
}
