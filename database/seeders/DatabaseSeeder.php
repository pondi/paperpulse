<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create an admin user in development
        if (app()->environment('local', 'development')) {
            User::factory()->create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'is_admin' => true,
            ]);
        }

        $this->call([
            MerchantSeeder::class,
            VendorSeeder::class,
        ]);
    }
}
