<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    private const COLORS = [
        '#EF4444', // red
        '#F59E0B', // amber
        '#10B981', // emerald
        '#3B82F6', // blue
        '#6366F1', // indigo
        '#8B5CF6', // violet
        '#EC4899', // pink
        '#14B8A6', // teal
        '#F97316', // orange
        '#84CC16', // lime
    ];

    public function definition(): array
    {
        $name = fake()->word();

        return [
            'user_id' => User::factory(),
            'name' => ucfirst($name),
            'color' => fake()->randomElement(self::COLORS),
        ];
    }

    public function withColor(string $color): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $color,
        ]);
    }
}
