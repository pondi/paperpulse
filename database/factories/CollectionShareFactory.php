<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionShare>
 */
class CollectionShareFactory extends Factory
{
    protected $model = CollectionShare::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'shared_by_user_id' => User::factory(),
            'shared_with_user_id' => User::factory(),
            'permission' => 'view',
            'shared_at' => now(),
            'expires_at' => null,
            'accessed_at' => null,
        ];
    }

    /**
     * Configure share with edit permission.
     */
    public function withEditPermission(): static
    {
        return $this->state(fn (array $attributes) => [
            'permission' => 'edit',
        ]);
    }

    /**
     * Configure expired share.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Configure expiring soon share.
     */
    public function expiresSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addHours(12),
        ]);
    }
}
