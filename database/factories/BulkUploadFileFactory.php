<?php

namespace Database\Factories;

use App\Enums\BulkUploadFileStatus;
use App\Models\BulkUploadFile;
use App\Models\BulkUploadSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BulkUploadFile>
 */
class BulkUploadFileFactory extends Factory
{
    protected $model = BulkUploadFile::class;

    public function definition(): array
    {
        $extension = $this->faker->randomElement(['pdf', 'jpg', 'png']);

        return [
            'uuid' => (string) Str::uuid(),
            'bulk_upload_session_id' => BulkUploadSession::factory(),
            'user_id' => User::factory(),
            'file_id' => null,
            'original_filename' => $this->faker->slug.'.'.$extension,
            'original_path' => null,
            'file_size' => $this->faker->numberBetween(1000, 5_000_000),
            'file_hash' => hash('sha256', (string) Str::uuid()),
            'file_extension' => $extension,
            'mime_type' => match ($extension) {
                'pdf' => 'application/pdf',
                'png' => 'image/png',
                default => 'image/jpeg',
            },
            'status' => BulkUploadFileStatus::Pending,
            'file_type' => null,
            'collection_ids' => null,
            'tag_ids' => null,
            'note' => null,
            's3_key' => null,
            'presigned_expires_at' => null,
            'job_id' => null,
            'error_message' => null,
        ];
    }

    public function duplicate(): static
    {
        return $this->state(fn () => ['status' => BulkUploadFileStatus::Duplicate]);
    }

    public function presigned(): static
    {
        return $this->state(fn () => [
            'status' => BulkUploadFileStatus::Presigned,
            'presigned_expires_at' => now()->addMinutes(30),
        ]);
    }
}
