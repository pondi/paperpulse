<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    public function definition(): array
    {
        $extension = $this->faker->randomElement(['pdf', 'jpg', 'png']);
        $fileType = $extension === 'pdf'
            ? 'application/pdf'
            : ($extension === 'png' ? 'image/png' : 'image/jpeg');

        return [
            'user_id' => User::factory(),
            'fileName' => $this->faker->slug.'.'.$extension,
            'fileExtension' => $extension,
            'fileType' => $fileType,
            'fileSize' => $this->faker->numberBetween(1000, 5_000_000),
            'fileImage' => null,
            'guid' => (string) Str::uuid(),
            'file_type' => 'receipt',
            'processing_type' => 'receipt',
            'status' => 'pending',
            's3_original_path' => null,
            's3_processed_path' => null,
            's3_archive_path' => null,
            's3_image_path' => null,
            'has_image_preview' => false,
            'image_generation_error' => null,
            'meta' => null,
            'uploaded_at' => now(),
            'file_created_at' => null,
            'file_modified_at' => null,
        ];
    }
}

