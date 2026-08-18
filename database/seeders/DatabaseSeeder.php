<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed court data first
        $this->call([
            CourtTypeSeeder::class,
            AmenitySeeder::class,
            TimeSlotSeeder::class,
            CourtSeeder::class,
            CourtPriceSeeder::class,
        ]);

        // Seed homepage content
        $this->call([
            BannerSeeder::class,
            PromotionSeeder::class,
            NewsSeeder::class,
            RefundRequestDemoSeeder::class,
            AdminSeeder::class,
        ]);

        // Create test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
