<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PublicShareAction;
use App\Models\PublicCollectionLink;
use App\Models\PublicShareAccessLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicShareAccessLog>
 */
class PublicShareAccessLogFactory extends Factory
{
    protected $model = PublicShareAccessLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_collection_link_id' => PublicCollectionLink::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'action' => PublicShareAction::View,
            'metadata' => null,
            'accessed_at' => now(),
        ];
    }

    public function forAction(PublicShareAction $action): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => $action,
        ]);
    }
}
