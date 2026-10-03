<?php

namespace Database\Factories;

use App\Models\ArchiveExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArchiveExport> */
class ArchiveExportFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'format' => 'pdf', 'filters' => [], 'total' => 100, 'expires_at' => now()->addDay()];
    }
}
