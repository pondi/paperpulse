<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        $name = fake()->sentence(2);

        return [
            'user_id' => User::factory(),
            'name' => rtrim($name, '.'),
            'description' => fake()->optional()->sentence(),
            'icon' => fake()->randomElement(Collection::ICONS),
            'color' => fake()->randomElement(Collection::COLORS),
            'is_archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
        ]);
    }

    public function withIcon(string $icon): static
    {
        return $this->state(fn (array $attributes) => [
            'icon' => $icon,
        ]);
    }

    public function withColor(string $color): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $color,
        ]);
    }
}
