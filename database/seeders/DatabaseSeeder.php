<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CourtTypeSeeder::class, AmenitySeeder::class, TimeSlotSeeder::class,
            CourtSeeder::class, CourtPriceSeeder::class, BannerSeeder::class,
            PromotionSeeder::class, NewsSeeder::class, RefundRequestDemoSeeder::class,
            AdminSeeder::class, ServiceItemSeeder::class, ReviewSeeder::class,
            ChatbotKnowledgeSeeder::class,
        ]);

        User::updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User', 'password' => bcrypt('password'),
            'phone' => '0900000000', 'role' => 'CUSTOMER', 'status' => 'ACTIVE',
        ]);
    }
}
