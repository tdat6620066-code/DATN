<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::updateOrCreate(['title' => 'SmashZone - Sân cầu lông hàng đầu'], [
            'image' => 'https://via.placeholder.com/1200x400?text=SmashZone+Banner+1',
            'link' => '/courts',
            'start_at' => Carbon::now(),
            'end_at' => null,
            'sort_order' => 1,
            'status' => 'ACTIVE',
        ]);

        Banner::updateOrCreate(['title' => 'Ưu đãi 20% cho khách hàng mới'], [
            'image' => 'https://via.placeholder.com/1200x400?text=SmashZone+Banner+2',
            'link' => '/courts',
            'start_at' => Carbon::now(),
            'end_at' => null,
            'sort_order' => 2,
            'status' => 'ACTIVE',
        ]);

        Banner::updateOrCreate(['title' => 'Sân VIP mới - Trải nghiệm tuyệt vời'], [
            'image' => 'https://via.placeholder.com/1200x400?text=SmashZone+VIP',
            'link' => '/courts',
            'start_at' => Carbon::now(),
            'end_at' => null,
            'sort_order' => 3,
            'status' => 'ACTIVE',
        ]);
    }
}
