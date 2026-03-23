<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Collection;
use App\Models\PublicCollectionLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicCollectionLink>
 */
class PublicCollectionLinkFactory extends Factory
{
    protected $model = PublicCollectionLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'created_by_user_id' => User::factory(),
            'label' => fake()->optional()->sentence(3),
            'is_password_protected' => false,
            'password_hash' => null,
            'expires_at' => null,
            'max_views' => null,
            'view_count' => 0,
            'is_active' => true,
            'last_accessed_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function passwordProtected(string $password = 'test1234'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_password_protected' => true,
            'password_hash' => bcrypt($password),
        ]);
    }

    public function withMaxViews(int $maxViews = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'max_views' => $maxViews,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withLabel(string $label): static
    {
        return $this->state(fn (array $attributes) => [
            'label' => $label,
        ]);
    }

    public function expiresIn(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays($days),
        ]);
    }
}
