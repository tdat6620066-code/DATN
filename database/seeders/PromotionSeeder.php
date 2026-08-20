<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        Promotion::updateOrCreate(['title' => 'Chiết khấu 10% khách hàng thường xuyên'], [
            'description' => 'Dành cho những khách hàng đặt sân 10 lần trở lên trong tháng. Nhận mã giảm giá 10% cho lần đặt tiếp theo',
            'image' => null,
            'start_at' => Carbon::now()->startOfMonth(),
            'end_at' => Carbon::now()->endOfMonth(),
            'status' => 'ACTIVE',
        ]);

        Promotion::updateOrCreate(['title' => 'Ưu đãi ngày lễ'], [
            'description' => 'Giảm 15% cho tất cả các sân trong tuần lễ. Mã giảm: HOLIDAY15',
            'image' => null,
            'start_at' => Carbon::now()->addMonths(1)->startOfMonth(),
            'end_at' => Carbon::now()->addMonths(1)->addDays(7),
            'status' => 'ACTIVE',
        ]);

        Promotion::updateOrCreate(['title' => 'Đặt sân nhóm - Tiết kiệm tối đa'], [
            'description' => 'Đặt 5 sân trở lên - Giảm 25% cho toàn bộ đơn hàng. Mã giảm: GROUP25',
            'image' => null,
            'start_at' => Carbon::now(),
            'end_at' => Carbon::now()->addMonths(3),
            'status' => 'ACTIVE',
        ]);
    }
}
