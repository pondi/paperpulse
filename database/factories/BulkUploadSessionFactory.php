<?php

namespace Database\Factories;

use App\Enums\BulkUploadSessionStatus;
use App\Models\BulkUploadSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BulkUploadSession>
 */
class BulkUploadSessionFactory extends Factory
{
    protected $model = BulkUploadSession::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'status' => BulkUploadSessionStatus::Pending,
            'total_files' => $this->faker->numberBetween(1, 100),
            'uploaded_count' => 0,
            'completed_count' => 0,
            'failed_count' => 0,
            'duplicate_count' => 0,
            'default_file_type' => 'receipt',
            'default_collection_ids' => null,
            'default_tag_ids' => null,
            'default_note' => null,
            'expires_at' => now()->addHours(24),
            'completed_at' => null,
        ];
    }

    public function uploading(): static
    {
        return $this->state(fn () => ['status' => BulkUploadSessionStatus::Uploading]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => BulkUploadSessionStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }
}
